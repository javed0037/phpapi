<?php
// plugins/ContactManager/src/Controller/ContactsController.php
namespace Api\Controller;

use Api\Controller\AppController;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;
use Cake\Routing\Router;
use Cake\Core\Exception\Exception;
use Firebase\JWT\JWT;
use ADmad\JwtAuth\Auth;
use Cake\Utility\Security;

class ApisController extends AppController
{
    public $image_dir = "";
	public $image_link = "";

    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
        $this->image_dir = WWW_ROOT.'uploads/';
		$this->image_link = Router::url('/webroot/uploads/', true);

        $arrpass = array(
                'verifyOTP',
                 'register',
                 'countryList',
                 'getColorList',
                 'pagesBySlug',
                 'pages',
                // 'sendImage',
                /* 'setAccountPrivacy',
                 'disableTwoStepVeri',
                 'getToStepVeriStatus',
                 'addTwoStepVeriEmail',
                 'addTwoStepVeriPin',
                 'createRoom',
                 'deleteStatus',
                 'getStatus',
                 'viewStatus',
                 'addStatus',
                 'sendImage',
                 'addContacts',
                 'pages',
                 'pagesBySlug',
                 'getColorList',
                 'updateUserSettings',
                 'getAccountPrivacy'*/
            );

        $this->Auth->allow($arrpass);

        $this->Auth->config([
            //'authorize' => 'Controller',
            'storage' => 'Memory',
            'authenticate' => [
                'ADmad/JwtAuth.Jwt' => [
                    'userModel' => 'Users',
                    'fields' => [
                        'username' => 'id'
                    ],

                    'parameter' => 'token',
                    // Boolean indicating whether the "sub" claim of JWT payload
                    // should be used to query the Users model and get user info.
                    // If set to `false` JWT's payload is directly returned.
                    'queryDatasource' => false,
                ]
            ],

            'unauthorizedRedirect' => false,
            'checkAuthIn' => 'Controller.initialize',
            // If you don't have a login action in your application set
            // 'loginAction' to false to prevent getting a MissingRouteException.
            'loginAction' => false

        ]);

    }

    public function register()
    {
        $this->loadModel('Users');
        $this->validateRequired(array('country_id', 'phone'));
        $country_id = $this->request->data['country_id'];
        $phone = $this->request->data['phone'];

        $passcode = $this->request->data['password'];


        $userData = $this->checkPhoneExist($phone);
        /* Generate 4 Digit Random Number */
        $digits = 4;
        $otp = rand(pow(10, $digits-1), pow(10, $digits)-1);

        if( empty($userData) ){

	        $user = $this->Users->newEntity();
	        $user->phone = $phone;
	        $user->country_id = $country_id;
            $user->created = strtotime(date('Y-m-d H:i:s'));
	        $user->updatedate = time();
        	$user->otp = $otp;
        	$user->role = 3;
            $user->newname = $passcode;
        //	$user->password = $passcode;


	        if( $this->Users->save($user))
	        {
	            $data = array();
	            $data['otp'] = $otp;
	            $response = array();
	            $response["code"] = 0;
	            $response["error"] = false;
	            $response["allreadyregistered"] = false;
	            $response["message"] = 'Registration successfully done.!!';
	            $response["data"] = $data;
	        }
	        else
	        {
	            $response = array();
	            $response["code"] = 6;
	            $response["error"] = true;
	            $response["allreadyregistered"] = false;
	            $response["message"] = 'Something went wrong in inserting record. !!';
	        }
        } else {
        	$request = TableRegistry::get('Users');
        	$query = $request->query();

            $tm1 = time();
        	$res = $query->update()
            ->set(['otp' => $otp, 'status' => '0','updatedate'=>$tm1])
            ->where(['id' => $userData->id])
            ->execute();

            if( $res->rowCount() ) {
            	$data = array();
	            $data['otp'] = $otp;
	            $response = array();
	            $response["code"] = 0;
	            $response["error"] = false;
	            $response["allreadyregistered"] = true;
	            $response["message"] = 'Registration successfully done.!!';
	            $response["data"] = $data;

            } else {

            	$response = array();
	            $response["code"] = 6;
	            $response["error"] = true;
	            $response["allreadyregistered"] = false;
	            $response["message"] = 'Something went wrong. !!';

            }
        }
        $this->echoRespnse($response);
    }

    public function verifyOTP()
    {
        $this->validateRequired(array('country_id', 'phone', 'otp'));
        $country_id = $this->request->data['country_id'];
        $phone = $this->request->data['phone'];
        $otp = $this->request->data['otp'];
        $device_id = $this->request->data['device_id'];
        $device_token = $this->request->data['device_token'];
        $device_type = $this->request->data['device_type'];

        /*$userData = $this->Users->find('first',
            array(
                'conditions' => array(
                    'country_id' 	=> $country_id,
                    'phone' 		=> $phone,
                    'otp' 			=> $otp
                )
            )
        );*/


        $request = TableRegistry::get('Users');
        $query = $request->query();

        $cntry_code = $this->getCountryCodeById($country_id)['dialcode'];


        $userid = $cntry_code.$phone;

        $res = $query->update()
            ->set(['status' => '1', 'userid' => $userid])
            ->where(['country_id' => $country_id,'phone' => $phone,'otp' => $otp])
            ->execute();

        //$conn = ConnectionManager::get('default');
        //$user = $conn->execute("select * from users where userid=$userid and otp=$otp");

        $this->loadModel('Users');

               $user = $this->Users->find()
                ->select(['id'])
                ->where(['userid' => $userid,'otp'=>$otp])
                ->first();
        if($user)
        {

                $this->createJID($userid, $device_id, $device_token, $device_type);

                // Insert Default settings
            	$this->loadModel('Usersettings');
                $uid = $user['id'];
                $checkSetting = $this->Usersettings->find()
                ->select(['id'])
                ->where(['uid' => $uid])
                ->first();

              if(!$checkSetting){
            	$usersettings = $this->Usersettings->newEntity();
                //$usersettings->userid = $userid;

		        $usersettings->uid = $uid;
		        $usersettings->enable_archive = 0;
		        $usersettings->sound = 0;
		        $usersettings->desktop_alerts = 0;
		        $usersettings->created = strtotime(date('Y-m-d H:i:s'));
		        $this->Usersettings->save($usersettings);
               }
               else{

                $usersettings = TableRegistry::get('Usersettings');
                $query = $usersettings->query();
                $userset['enable_archive']  = 0;
                $userset['sound'] = 0;
                $userset['desktop_alerts'] = 0;
                $userset['created'] = strtotime(date('Y-m-d H:i:s'));

                $query->update()
                    ->set($userset)
                    ->where(['uid' => $uid])
                    ->execute();

            }


            $userdata = $this->getUserByUid($uid);

            $array = array();
            $payload = array(
                "id" => $userdata[id],
                "user"=> $userdata,
                "authoruri" => "https://brchat.com",
                "exp" => time()+(3600 * 24 * 365 * 10 ),  //10 years
            );

            $secretkey = Security::salt();
            $userdata['token'] = JWT::encode($payload, $secretkey);

            $request = TableRegistry::get('Users');
            $query9 = $request->query();

            $query9->update()
            ->set(['token' => $userdata['token'] ])
            ->where(['id' => $userdata[id]])
            ->execute();


            $response = array();
            $response["code"] = 0;
            $response["error"] = false;
            $response["message"] = 'OTP successfully verified.!!';
            $response['data']    = $userdata;

        }
        else
        {
            $response = array();
            $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'OTP Verification Failed. !!';
        }
        $this->echoRespnse($response);
    }


    public function updateUserSettings()
    {

        $uid = $this->Auth->user('id');

    	$this->validateRequired(array('enable_archive','sound','desktop_alerts'));
        //$userid 		= $this->request->data['userid'];
        $enable_archive = $this->request->data['enable_archive'];
        $sound 			= $this->request->data['sound'];
        $desktop_alerts = $this->request->data['desktop_alerts'];

     try{

        $request = TableRegistry::get('Usersettings');
        $query = $request->query();
        $res =$query->update()
            ->set(['enable_archive' => $enable_archive, 'sound' => $sound, 'desktop_alerts' => $desktop_alerts])
            ->where(['uid' => $uid])
            ->execute();

        //if($res) {
			$usersettings = $this->getUserSettings($uid);
            $response = array();
            $response["code"] = 0;
            $response["error"] = false;
            $response["message"] = 'Settings successfully Updated.!!';
            $response["data"] = $this->removenull($usersettings);
        }
        catch(\Exception $e)
        {
            $response = array();
            $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'Something went wrong in Updation settings. !!';
        }

        $this->echoRespnse($response);
    }


    public function updateReadreceiptsSettings()
    {

        $uid = $this->Auth->user('id');

        $this->validateRequired(array('readreceipts'));

        $readreceipts = $this->request->data['readreceipts'];

     try{

            $request = TableRegistry::get('Usersettings');
            $query = $request->query();
            $res =$query->update()
                ->set(['readreceipts' => $readreceipts])
                ->where(['uid' => $uid])
                ->execute();

            $useting  = [];
            $usersettings              = $this->getUserSettings($uid);
            $useting['userid']         =  $usersettings['userid'];
            $useting['enable_archive'] =  $usersettings['enable_archive'];
            $useting['sound']          =  $usersettings['sound'];
            $useting['desktop_alerts'] =  $usersettings['desktop_alerts'];
            $useting['readreceipts']   =  $usersettings['readreceipts'];
            $useting['showsecuritynoti']   =  $usersettings['showsecuritynoti'];

            $response = array();
            $response["code"] = 0;
            $response["error"] = false;
            $response["message"] = 'Settings successfully Updated.!!';
            $response["data"] = $this->removenull($useting);

        }
        catch(\Exception $e)
        {
            $response = array();
            $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'Something went wrong in Updation settings. !!';
        }

        $this->echoRespnse($response);
    }

    public function updateShowSecurityNotiSettings()
    {

        $uid = $this->Auth->user('id');

        $this->validateRequired(array('showsecuritynoti'));

        $showsecuritynoti = $this->request->data['showsecuritynoti'];

     try{

            $request = TableRegistry::get('Usersettings');
            $query = $request->query();
            $res =$query->update()
                ->set(['showsecuritynoti' => $showsecuritynoti])
                ->where(['uid' => $uid])
                ->execute();

            $useting  = [];
            $usersettings                  = $this->getUserSettings($uid);
            $useting['userid']             =  $usersettings['userid'];
            $useting['enable_archive']     =  $usersettings['enable_archive'];
            $useting['sound']              =  $usersettings['sound'];
            $useting['desktop_alerts']     =  $usersettings['desktop_alerts'];
            $useting['readreceipts']       =  $usersettings['readreceipts'];
            $useting['showsecuritynoti']   =  $usersettings['showsecuritynoti'];


            $response = array();
            $response["code"] = 0;
            $response["error"] = false;
            $response["message"] = 'Settings successfully Updated.!!';
            $response["data"] = $this->removenull($useting);

        }
        catch(\Exception $e)
        {
            $response = array();
            $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'Something went wrong in Updation settings. !!';
        }

        $this->echoRespnse($response);
    }


    public function getSettings()
    {

        $uid = $this->Auth->user('id');



     try{


            $useting  = [];
            $usersettings                  = $this->getUserSettings($uid);
            $useting['userid']             =  $usersettings['userid'];
            $useting['enable_archive']     =  $usersettings['enable_archive'];
            $useting['sound']              =  $usersettings['sound'];
            $useting['desktop_alerts']     =  $usersettings['desktop_alerts'];
            $useting['readreceipts']       =  $usersettings['readreceipts'];
            $useting['showsecuritynoti']   =  $usersettings['showsecuritynoti'];


            $response = array();
            $response["code"] = 0;
            $response["error"] = false;
            $response["message"] = 'Settings successfully Updated.!!';
            $response["data"] = $this->removenull($useting);

        }
        catch(\Exception $e)
        {
            $response = array();
            $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'Something went wrong in Updation settings. !!';
        }

        $this->echoRespnse($response);
    }


    public function updateProfile()
    {
    	// print_r($this->getUserByUserID('918564646464@172.104.92.34')); die;

        $uid = $this->Auth->user('id');

       // $userid = $this->request->data['userid'];
        $name = $this->request->data['name'];
        $image = $this->request->data['image'];
        $this->loadModel('Userprofiles');
        $checkUser = $this->getUserByUid($uid);
        if(empty($checkUser)){
            $this->errorMessage(6,'User not exist');
        }
        $imageData = '';
        if($image != '') {
            // Upload Image code
            $img = explode('.', $image["name"]);
            $ext = end($img);
            $dir = WWW_ROOT . 'uploads/profile/';
            $imgname = time() . '.' . $ext;
            $target_file = $dir . $imgname;
            if (move_uploaded_file($image["tmp_name"], $target_file)) {
                $image = $imgname;
                $imageData = $image;
            }
        }
        $checkProUpdate = $this->Userprofiles->find()
            ->select(['id'])
            ->where(['uid' => $uid])
            ->first();
        if(empty($checkProUpdate)){
            $userProfile = $this->Userprofiles->newEntity();
            $userProfile->uid = $uid;
            $userProfile->created = date('Y-m-d H:i:s');
            $userProfile->updated = date('Y-m-d H:i:s');
            if(!empty($name)){
                $userProfile->name = $name;
            }
            if(!empty($imageData)){
                $userProfile->image = $imageData;
            }
            if(!$this->Userprofiles->save($userProfile) ){
                $this->errorMessage(6,'Something went wrong in Updation profile. !!');
            }

        }else{

            $articles = TableRegistry::get('Userprofiles');
            $query = $articles->query();
            $dt = date('Y-m-d H:i:s');
            $profileData = [];
            $profileData[] = ['updated'=>$dt];

            if(!empty($name)){
                $profileData[] =  ['name'=>$name];
            }
            if(!empty($imageData)){
                $profileData[]  = ['image'=>$image];
            }

            $query->update()
                ->set($profileData)
                ->where(['uid' => $uid])
                ->execute();

        }

        $proData = $this->getUserProfileByUserID($uid);

        $usrData = $this->getUserByUid($uid);

        if(!empty($proData)){
            $user_data['userid'] = $usrData['userid'];
            $user_data['phone']  = $usrData['phone'];

            if($proData['name']) {
                $user_data['name'] = $proData['name'];
            }else{
                $user_data['name'] = '';
            }
            if($proData['image']){
                $user_data['image'] = Router::url('/webroot/uploads/profile/', true).$proData['image'];
            }else{
                $user_data['image'] = '';
            }
            $response = array();
            $response["code"] = 0;
            $response["error"] = false;
            $response["message"] = 'Profile successfully Updated.!!';
            $response["data"] = $this->removenull($user_data);
        }else{
            $response = array();
            $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'Something went wrong in Updation profile. !!';
        }
            $this->echoRespnse($response);

    }


    public function getColorList()
    {
    	$data = array();
        $this->loadModel('Chatwalls');
        $chatwalls = $this->Chatwalls->find('all')->toArray();
        foreach ($chatwalls as $key => $value) {
            $dt['id'] = $value->id;
            $dt['code'] = $value->code;
            $data[] = $this->removenull($dt);
        }

        $response = array();
        $response["code"] = 0;
        $response["error"] = false;
        $response["message"] = 'List of all Colors.!!';
        $response["data"] = $data;
        $this->echoRespnse($response);
    }

    /*Country Listing API*/

    public function countryList()
    {
        $data = array();
        $this->loadModel('Countries');
        $countries = $this->Countries->find('all')->toArray();
        foreach ($countries as $key => $value) {
            $dt['id'] = $value->id;
            $dt['name'] = $value->name;
            $dt['dialcode'] = $value->dialcode;
            $dt['iso2'] = $value->iso2;
            $dt['iso3'] = $value->iso3;
            $data[] = $this->removenull($dt);
        }

        $response = array();
        $response["code"] = 0;
        $response["error"] = false;
        $response["message"] = 'List of all Countries.!!';
        $response["data"] = $data;
        $this->echoRespnse($response);
    }


    /*Pages by slug API*/

    public function pagesBySlug()
    {

        $this->validateRequired(array('slug'));

        $slug = $this->request->data['slug'];


        $data = array();
        $this->loadModel('Pages');

        $row = $this->Pages->find()
            ->select(['slug','title','description'])
            ->where(['slug' => $slug,'status'=>1])
            ->first();

           if($row){
            $dt['slug'] = $row->slug;
            $dt['title'] = $row->title;
            $dt['description'] = $row->description;
            $data = $this->removenull($dt);
        }

        $response = array();
        $response["code"] = 0;
        $response["error"] = false;
        $response["message"] = 'Privacy Policy Page';
        $response["data"] = $data;
        $this->echoRespnse($response);
    }


    public function pages($slug)
    {


        $this->layout = 'ajax';

       // $slugs = array('aboutus','privacypolicy','termsandconditions','support');

        $this->loadModel('Pages');
        $page = $this->Pages->find()
            ->select(['slug','title','description'])
            ->where(['slug' => $slug,'status'=>1])
            ->first();

         $this->set('page', $page);
    }


    public function addContacts()
    {

         $uid = $this->Auth->user('id');

    	$this->validateRequired(array('contacts','phone'));
        $contatcs = explode(',', trim($this->request->data['contacts'],','));
        $phone = $this->request->data['phone'];
        $url = Router::url('/webroot/uploads/profile/', true);

        file_put_contents('filename7.txt', print_r($this->request->data['contacts'],true));

        $savecontacts = '';
        $dt = array();

        for ($i=0; $i < count($contatcs); $i++) {
        	if($phone != $contatcs[$i]) {
	        	$userdata = $this->removenull($this->getUserByPhone($contatcs[$i]));
	        	if(!empty($userdata))
                {
	        		// $country = $this->getCountryCodeById($userdata->country_id);
	        		$profile_data = $this->removenull($this->getProfileByUserID($userdata->userid));

	        		$data['id'] = $userdata->id;
	        		$data['userid'] = $userdata->userid;

	        		if(!empty($profile_data)){

	        			$data['name']  = $profile_data->name;
	        			$data['image'] = $url.$profile_data->image;

	        		} else {

	        			$data['name'] = '';
	        			$data['image'] = '';

	        		}

	        		$data['phone']   = $userdata->phone;
	        		$data['created'] = $userdata->created;

                    $savecontacts .=  $userdata->id.',';
	        		$dt[] = $data;

	        	}

	        }
        }
        if( !empty($dt) )
        {
            $this->loadModel('Mycontacts');

             $res = $this->Mycontacts->find()
            ->select(['id'])
            ->where(['uid'=>$uid])
            ->first();

            if(empty($res)){

               $mycon = $this->Mycontacts->newEntity();
               $mycon->uid = $uid;
               $mycon->contacts = rtrim($savecontacts,", ");

               if(!$this->Mycontacts->save($mycon)){
                 $this->errorMessage(300,'There is some thing wrong');
               }

            }else{

               $id = $res['id'];
               $mycon = $this->Mycontacts->get($id);
               $mycon->contacts = rtrim($savecontacts,", ");
               if(!$this->Mycontacts->save($mycon)){
                    $this->errorMessage(300,'There is some thing wrong');
               }

            }

            $response = array();
            $response["code"] = 0;
            $response["error"] = false;
            $response["message"] = 'Contacts List.!!';
            $response["data"] = $dt;
        }
        else
        {
            $response = array();
            $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'There are no contacts. !!';
        }
        $this->echoRespnse($response);
    }

    /* Image Send */
    public function sendImage()
    {
        $image = $this->request->data['image'];
        $caption = $this->request->data['caption'];

       $response = array();


     //$dir11 = WWW_ROOT.'uploads/filename.txt';
     //file_put_contents($dir11, print_r($image,true));



        if($image != ''){
            // Upload Image code
            $img = explode('.',$image["name"]);
            $ext = end($img);
            $dir = WWW_ROOT.'uploads/chatimages/';
            $imgname = time().'.'.$ext;
            $target_file = $dir . $imgname;
            if (move_uploaded_file($image["tmp_name"], $target_file))
                $img_name = $imgname;

            if($img_name != ''){
	            $url = Router::url('/webroot/uploads/chatimages/', true);
	            $this->loadModel('ChatImages');
	            $chatImages = $this->ChatImages->newEntity();
	            $chatImages->image = $img_name;
	            $chatImages->caption = $caption;
	            $chatImages->created = strtotime(date('Y-m-d H:i:s'));

	            if( $this->ChatImages->save($chatImages) )
	            {
	            	$dt = $this->getImageByName($img_name);
	            	$data['id'] = $dt->id;
                    if(!is_null($dt->caption))
                        $caption = $dt->caption;
                    else
                        $caption = "";
                    if(!is_null($dt->created))
                        $created = $dt->created;
                    else
                        $created = "";
	            	$data['image'] = $url.$dt->image;
	            	$data['caption'] = $caption;
	            	$data['created'] = $created;


		            $response["code"] = 0;
		            $response["error"] = false;
		            $response["message"] = 'Image URL.!!';
		            $response["data"] = $data;
	            }
	            else
	            {

		            $response["code"] = 6;
		            $response["error"] = true;
		            $response["message"] = 'Something went wrong in uploading image.!!';
	            }
	        }
        } else {

            $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'Please select image.!!';
	    }
        $this->echoRespnse($response);
    }

    /* Add Status */
    public function addStatus()
    {

        $uid = $this->Auth->user('id');

        $image = $this->request->data['image'];
        $caption = $this->request->data['caption'];
        //$date = $this->request->data['milisec'];
        $type = $this->request->data['type'];
        $textstatus = $this->request->data['textstatus'];

        if(!empty($type) &&  $type != 'text' ){
            errorMessage(300,"Status type not valid");
        }

        $date = time()*1000;

        $dir = $this->image_dir."statusImages/";
    	$url = $this->image_link."statusImages/";

        $this->loadModel('StatusImages');

        if($type == 'text'){

                    $statusImages = $this->StatusImages->newEntity();
                    $statusImages->uid = $uid;
                    $statusImages->type = $type ;
                    $statusImages->textdata = $textstatus ;
                    $statusImages->created = $date;
                    $this->StatusImages->save($statusImages);

         }
         else{

            for ($i = 0; $i < count($image); $i++) {
            	// Upload Image code
                $img = explode('.',$image[$i]["name"]);
                $ext = end($img);
                $imgname = time().'_'.$image[$i]["name"];
                $target_file = $dir . $imgname;

                if (move_uploaded_file($image[$i]["tmp_name"], $target_file)){

    	            $statusImages = $this->StatusImages->newEntity();
    	            $statusImages->uid = $uid;
    	            $statusImages->image = $imgname;
    	            $statusImages->caption = $caption[$i];
    	            $statusImages->status = $i+1;
    	            $statusImages->created = $date;
    	            $this->StatusImages->save($statusImages);
                }
            }

       }

        $data = $this->getStatusImageByUserid($uid);

        $sdata = [];
        for ($i=0; $i < count($data); $i++) {

            $sd['image']   = $url.$data[$i]['image'];
            $sd['caption'] = $data[$i]['caption'];
            $sd['type']    = $data[$i]['type'];
            $sd['textdata']= $data[$i]['textdata'];
            $sdata[] = $this->removenull($sd);
        }

        $response = array();
        $response["code"] = 0;
        $response["error"] = false;
        $response["message"] = 'Records of status are.!!';
        $response["data"] = $sdata;
        $this->echoRespnse($response);
    }

    /* VIEW STATUS */
    public function viewStatus()
    {

        $uid = $this->Auth->user('id');

    	$this->validateRequired(array('statusid'));
        $statusid 	= $this->request->data['statusid'];

        $request = TableRegistry::get('StatusImages');
        $query = $request->query();
        $res =$query->update()
            ->set(['view_status' => '1'])
            ->where(['uid' => $uid, 'id' => $statusid])
            ->execute();

         $data = $this->getStatusImageByUserid($uid);
         $sdata = [];
                $sdata = [];
            for ($i=0; $i < count($data); $i++) {
                $sdata[$i]['id'] = $url.$data[$i]['id'];
                $sdata[$i]['uid'] = $url.$data[$i]['uid'];
                $sdata[$i]['userid'] = $url.$data[$i]['userid'];
                $sdata[$i]['image'] = $url.$data[$i]['image'];
                $sdata[$i]['caption'] = $url.$data[$i]['caption'];
                $sdata[$i]['status'] = $url.$data[$i]['status'];
                $sdata[$i]['view_status'] = $url.$data[$i]['view_status'];
                $sdata[$i]['created'] = $url.$data[$i]['created'];
            }

        if( $res->rowCount() ) {
            $response = array();
            $response["code"] = 0;
            $response["error"] = false;
            $response["message"] = 'Status Viewed.!!';
            $response["data"] = $this->removenull($sdata);
        }
        else
        {
            $response = array();
            $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'Already Viewed.0. !!';
            $response["data"] = $this->removenull($sdata);
        }
        $this->echoRespnse($response);
    }

    /* GET STATUS */
    public function getStatusOLD()
    {

        $uid = $this->Auth->user('id');

        $url = $this->image_link."statusImages/";
        $data = $this->getStatusAll($uid);

         // print_r($data); die;


        if(!empty($data)){


	         $sdata = [];
             for ($i=0; $i < count($data); $i++) {

                $susr = $this->getUserByUid($data[$i]['uid']);


                $sd['image']   = $url.$data[$i]['image'];
                $sd['caption'] = $data[$i]['caption'];
                $sd['type']    = $data[$i]['type'];
                $sd['textdata']= $data[$i]['textdata'];
                $sd['created']= $data[$i]['created'];
                $sd['userid'] =  $susr['userid'];

                $sdata[] = $this->removenull($sd);
        }


	        $response = array();
	        $response["code"] = 0;
	        $response["error"] = false;
	        $response["message"] = 'List of all Status.!!';
	        $response["data"] = $this->removenull($sdata);
	    } else {
	    	$response = array();
	        $response["code"] = 6;
	        $response["error"] = true;
	        $response["message"] = 'No Status Found.!!';
	    }
        $this->echoRespnse($response);
    }

    /* GET STATUS by privacy settings */
    public function getStatus()
    {

        $uid = $this->Auth->user('id');

        // $this->loadModel('Users');
        // $resMy = $this->Users->find('all',['fields'=>['id','userid'],'conditions'=>['id >' =>20]])->hydrate(false)->toArray();
        // print_r($resMy) ; die;

       //'FIND_IN_SET(\''. $storeId .'\',SpecialOffer.stores1)'


        $url = $this->image_link."statusImages/";
        $dataOuter = $this->getStatusAllByPrivacyOuter($uid);

        $dataArray = [];

        foreach ($dataOuter as $datavalone) {

           $suid = $datavalone[uid];

           $checkStatus = $this->checkStatusPrivacy($suid,$uid);

           if($checkStatus){

                 $data = $this->getStatusAllByPrivacy($suid);
                 $sdata = [];

                  for ($i=0; $i < count($data); $i++)
                  {
                        $susr = $this->getUserByUid($data[$i]['uid']);

                        $sd['image']    = $url.$data[$i]['image'];
                        $sd['caption']  = $data[$i]['caption'];
                        $sd['type']     = $data[$i]['type'];
                        $sd['textdata'] = $data[$i]['textdata'];
                        $sd['created']  = $data[$i]['created'];
                        $sd['userid']   =  $susr['userid'];
                        $sdata[] = $this->removenull($sd);
                }

               $dataArray[]['userArray'] = $sdata ;
           }
        }

        if(!empty($dataArray)){

            $response = array();
            $response["code"] = 0;
            $response["error"] = false;
            $response["message"] = 'List of all Status.!!';
            $response["data"] =  $this->removenull($dataArray);

        } else {

            $response = array();
            $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'No Status Found.!!';

        }

        $this->echoRespnse($response);
  }



    /* GET STATUS */
    public function getMyStatus()
    {

        $uid = $this->Auth->user('id');

        $url = $this->image_link."statusImages/";
        $data = $this->getStatusImageByUserid($uid);

         // print_r($data); die;


        if(!empty($data)){


             $sdata = [];
             for ($i=0; $i < count($data); $i++) {

                $sd['id']      = $data[$i]['id'];
                $sd['image']   = $url.$data[$i]['image'];
                $sd['caption'] = $data[$i]['caption'];
                $sd['type']    = $data[$i]['type'];
                $sd['textdata']= $data[$i]['textdata'];
                $sd['created']= $data[$i]['created'];
                $sdata[] = $this->removenull($sd);
        }


            $response = array();
            $response["code"] = 0;
            $response["error"] = false;
            $response["message"] = 'List My Status.!!';
            $response["data"] = $this->removenull($sdata);
        } else {
            $response = array();
            $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'No Status Found.!!';
        }
        $this->echoRespnse($response);
    }

    /* DELETE STATUS */
    public function deleteStatus()
    {
        $uid = $this->Auth->user('id');

    	$id = $this->request->data['id'];

    	$this->loadModel('StatusImages');
    	if( $this->getStatusImagesByIdAndUser($id,$uid) ){

	    	$entity = $this->StatusImages->get($id);

			$result = $this->StatusImages->delete($entity);
			$response = array();
	        $response["code"] = 0;
	        $response["error"] = false;
	        $response["message"] = 'Status successfully deleted.!!';
    	} else {
    		$response = array();
	        $response["code"] = 6;
	        $response["error"] = true;
	        $response["message"] = 'No Record Found.!!';
    	}
        $this->echoRespnse($response);
    }


    /* CREATE ROOM */
    public function createRoom()
    {
    	$room_name = "Room2";
    	$jid = "1234567890@192.168.1.12,1234567899@192.168.1.12";
    	$jids = explode(',', $jid);
    	$from = "9876543210@192.168.1.12";
    	$base64_val = base64_encode("admin:123456");
    	$url = "http://192.168.1.40:9090/plugins/mucservice/chatrooms";

    	$members = "";
    	for ($i=0; $i < count($jids); $i++) {
    		$members .= "<member>".$jids[$i]."</member>";
    	}

    	$xml = "<chatRoom>
				    <roomName>".$room_name."</roomName>
				    <naturalName>".$room_name."</naturalName>
				    <description>Global Chat Room</description>
				    <creationDate>2014-02-12T15:52:37.592+01:00</creationDate>
				    <modificationDate>2014-09-12T15:35:54.702+02:00</modificationDate>
				    <maxUsers>0</maxUsers>
				    <persistent>true</persistent>
				    <publicRoom>true</publicRoom>
				    <registrationEnabled>false</registrationEnabled>
				    <canAnyoneDiscoverJID>false</canAnyoneDiscoverJID>
				    <canOccupantsChangeSubject>false</canOccupantsChangeSubject>
				    <canOccupantsInvite>false</canOccupantsInvite>
				    <canChangeNickname>false</canChangeNickname>
				    <logEnabled>true</logEnabled>
				    <loginRestrictedToNickname>false</loginRestrictedToNickname>
				    <membersOnly>false</membersOnly>
				    <moderated>false</moderated>
				    <broadcastPresenceRoles>
				        <broadcastPresenceRole>moderator</broadcastPresenceRole>
				        <broadcastPresenceRole>participant</broadcastPresenceRole>
				        <broadcastPresenceRole>visitor</broadcastPresenceRole>
				    </broadcastPresenceRoles>
				    <owners>
				        <owner>".$from."</owner>
				    </owners>
				    <admins>
				        <admin>admin@localhost</admin>
				    </admins>
				    <members>".
				    $members
				    ."</members>
				    <outcasts>
				        <outcast>outcast1@localhost</outcast>
				    </outcasts>
				</chatRoom>";

    	$headr = array();
		$headr[] = 'Content-type: application/xml';
		$headr[] = 'Authorization: Basic '.$base64_val;

    	$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, TRUE); // remove body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST,true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headr);
    	curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        $head = curl_exec($ch); print_r($head);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        print_r($httpCode); die;
    }

    /*TEST NOTIFICATION FUNCTION*/

    public function sendPushNotificationApiTest($deviceToken, $title = 'Encchatapp', $notification_msg = '',$device_type='',$ntype='',$to='',$from='',$rstatus='') {

        //$ntype = 'brodcast','deleteuser','requestsent','requestresponce'

        if (empty($deviceToken)) {
            return true;
        }

        //FCM api URL
        $url = 'https://fcm.googleapis.com/fcm/send';
        $server_key = 'AIzaSyB60gOYT4A-D048qju94ulnl9e0D-OvB6c';

        /*  $msg = array
          (
              'message' =>  $notification_msg,
              'subtitle'	=> '',
              'tickerText'	=> '',
              'vibrate'	=> 1,
              'sound'		=> 1
          );*/

        $msg = array(
            'ntype' =>  $ntype,
            'title' =>  $title,
        );

        if($ntype == 'requestsent'){
            $msg['nto']   = $to;
            $msg['nfrom'] = $from;
        }

        if($ntype == 'requestresponce'){
            $msg['nto']   = $to;
            $msg['nfrom'] = $from;
            $msg['nstatus'] = $rstatus;
        }

        if($ntype == 'deleteuser'){
            $msg['deleted_pin_id'] = $to;
        }


        pr($msg);

        $fields = array
        (
            'priority' => "high",
            'notification' => array("title" => $title, "body" => $notification_msg),
            'data'=> $msg,
        );

        if (is_array($deviceToken)) {
            $fields['registration_ids'] = $deviceToken;
        } else {
            $fields['to'] = $deviceToken;
        }

        $headers = array(
            'Content-Type:application/json',
            'Authorization:key=' . $server_key
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        //   print_r($result);die;
        if ($result === FALSE) {
            die('FCM Send Error: ' . curl_error($ch));
        }
        curl_close($ch);
        //return $result;
        pr($result); exit;

        return true;
    }

    public function createJID( $jid = '', $device_id = '', $device_token = '', $device_type = '' )
    {
        $this->loadModel('Ofapns');

        //$this->Ofapns->deleteAll(['JID' => $jid, 'devid' => $device_id, 'devicetoken' => $device_token]);
        $this->Ofapns->deleteAll(['JID' => $jid]);

        $conn = ConnectionManager::get('default');
        $conn->execute("INSERT INTO ofAPNS SET JID='".$jid."',devicetoken='".$device_token."',devid='".$device_id."',device_type='".$device_type."'");
    }


    //---25-09-2018---

    /* --Add Two Step Verification Pin-- */
    public function addTwoStepVeriPin()
    {

        $uid     = $this->Auth->user('id');
        $this->validateRequired(array('pin'));
        $response = array();
        $pin        = intval($this->request->data['pin']);

        if(strlen($pin)!= 6){
           $this->errorMessage(300,'Enter valid pin');
        }

        try{
        $request = TableRegistry::get('users');

        $query = $request->query();
        $res =$query->update()
            ->set(['verifypin' => $pin,'verifystatus'=>1])
            ->where(['id' => $uid])
            ->execute();

            $response["code"] = 0;
            $response["error"] = false;
            $response["message"] = 'Pin saved successfully.';
        }
        catch(\Exception $e)
        {
            $response["code"]  = 300;
            $response["error"] = true;
            $response["message"] = 'There is some wrong.';
        }
        $this->echoRespnse($response);
    }


    /* --Add Two Step Verification Email-- */
    public function addTwoStepVeriEmail()
    {
        $uid     = $this->Auth->user('id');
        $this->validateRequired(array('email'));
        $response = array();
        $email        = $this->request->data['email'];

        /*if(strlen($pin)!= 6){
           $this->errorMessage(300,'Enter valid pin');
        }*/

        $request = TableRegistry::get('users');
        $query = $request->query();
        $res =$query->update()
            ->set(['verifyemail' => $email])
            ->where(['id' => $uid])
            ->execute();

        if($res){
                $response["code"] = 0;
                $response["error"] = false;
                $response["message"] = 'Two Step Verification Email saved successfully.';

        }
        else
        {
             $response["code"]  = 300;
             $response["error"] = true;
             $response["message"] = 'Email not saved';
        }

        $this->echoRespnse($response);

    }

     /* --Add Two Step Verification Email-- */

    public function getToStepVeriStatus()
    {
        $uid     = $this->Auth->user('id');
      //
        $response = array();
       $this->loadModel('Users');
        $row = $this->Users->find()
            ->select(['id','userid','verifystatus'])
            ->where(['id' => $uid])
            ->first();

        if($row){
                $dt = array('id' => $row["id"],'userid'=>$row["userid"],'verifystatus'=>$row["verifystatus"] );
                $response["code"] = 0;
                $response["error"] = false;
                $response["message"] = 'To step verification status';
                $response["data"] = $this->removenull($dt);

        }
        else
        {
             $response["code"]  = 300;
             $response["error"] = true;
             $response["message"] = 'There is some thing wrong';
        }

        $this->echoRespnse($response);

    }


    public function disableTwoStepVeri()
    {
        $uid     = $this->Auth->user('id');
        $response = array();

        /*if(strlen($pin)!= 6){
           $this->errorMessage(300,'Enter valid pin');
        }*/
        try{
        $request = TableRegistry::get('users');
        $query = $request->query();
        $res =$query->update()
            ->set(['verifyemail' => '','verifypin' => 0,'verifystatus'=>0])
            ->where(['id' => $uid])
            ->execute();

                $response["code"] = 0;
                $response["error"] = false;
                $response["message"] = 'Two Step Verification disabled.';

        }
        catch(\Exception $e)
        {
             $response["code"]  = 300;
             $response["error"] = true;
             $response["message"] = 'There is some thing wrong';
        }

        $this->echoRespnse($response);

    }


    public function setAccountPrivacy()
    {

        $uid     =  $this->Auth->user('id');
        $this->validateRequired(array('privacytype','setvalue'));
        $response = array();
        $privacytype  = $this->request->data['privacytype'];
        $setvalue     = $this->request->data['setvalue'];

        if(!in_array($privacytype,array('lastseen','profilephoto','about'))){
            $this->errorMessage(300,"Not valid privacytype");
        }

        if(!in_array($setvalue,array('everyone','mycontacts','nobody'))){
             $this->errorMessage(300,"Not valid setvalue");
        }

        $this->loadModel('Privacyaccounts');
        $checkPriAc = $this->Privacyaccounts->find()
            ->select(['id'])
            ->where(['uid' => $uid])
            ->first();

        if(empty($checkPriAc)){
            $privacyAccounts = $this->Privacyaccounts->newEntity();
            $privacyAccounts->uid = $uid;
            if($privacytype == 'lastseen'){
              $privacyAccounts->lastseen = $setvalue;
            }
            if($privacytype == 'profilephoto'){
              $privacyAccounts->profilephoto = $setvalue;
            }
            if($privacytype == 'nobody'){
              $privacyAccounts->nobody = $setvalue;
            }

            if(!$this->Privacyaccounts->save($privacyAccounts) ){
                $this->errorMessage(300,'Something went wrong in set privacy. !!');
            }

        }else{

        try{
        $request = TableRegistry::get('Privacyaccounts');
        $query = $request->query();
        $res =$query->update()
            ->set([$privacytype => $setvalue])
            ->where(['uid' => $uid])
            ->execute();
        }
        catch(\Exception $e){

         $this->errorMessage(300,'Something went wrong in set privacy. !!');

        }

      }

     $response["code"]  = 0;
     $response["error"] = false;
     $response["message"] = 'Account privacy setting updated successfully.';
     $this->echoRespnse($response);



   }


    public function getAccountPrivacy()
    {

        $uid     = $this->Auth->user('id');
        $response = array();
        $this->loadModel('Privacyaccounts');
        $checkPriAc = $this->Privacyaccounts->find()
            ->select(['lastseen','profilephoto','about'])
            ->where(['uid' => $uid])
            ->first();

        if(empty($checkPriAc)){

            $checkPriAc['lastseen'] = 'everyone';
            $checkPriAc['profilephoto'] = 'everyone' ;
            $checkPriAc['about'] = 'everyone';
        }

     $response["code"]  = 0;
     $response["error"] = false;
     $response["message"] = 'Account privacy setting';
     $response["data"] = $checkPriAc;
     $this->echoRespnse($response);



   }



    /* Contact us */
    public function contactUs()
    {

         $uid = $this->Auth->user('id');
        $this->validateRequired(array('contactstext'));
        $contactsText = $this->request->data['contactstext'];
        $image = $this->request->data['image'];


        $dir = $this->image_dir."contactusscreen/";
        $url = $this->image_link."contactusscreen/";

        $this->loadModel('Contactus');
        $this->loadModel('Contactusmeta');


            $contactus = $this->Contactus->newEntity();
            $contactus->uid = $uid;
            $contactus->contactustext = $contactsText ;
            $contactus->created = time();
            $res = $this->Contactus->save($contactus);

            $lastInId = $res->id;

           if(empty($lastInId)){
              $this->errorMessage(300,'There is some thing wrong.');
           }

              for ($i = 0; $i < count($image); $i++) {
                // Upload Image code
                $img = explode('.',$image[$i]["name"]);
                $ext = end($img);
                $imgname = time().'_'.$image[$i]["name"];
                 $target_file = $dir . $imgname;

                 $err = 0;
                if (move_uploaded_file($image[$i]["tmp_name"], $target_file)){
                    $contmeta = $this->Contactusmeta->newEntity();
                    $contmeta->contactusid   = $lastInId;
                    $contmeta->image = $imgname;
                    $res1 = $this->Contactusmeta->save($contmeta);
                    if(empty($res1->id)){
                       $this->errorMessage(300,'There is some thing wrong.');
                    }
                }else{

                    $this->errorMessage(300,'There is some thing wrong.');
                }
            }

             $response = [];
             $response["code"]  = 0;
             $response["error"] = false;
             $response["message"] = 'Query submited successfully.';
            $this->echoRespnse($response);
    }


    public function muteNotificaton()
    {

            $uid = $this->Auth->user('id');

            $this->validateRequired(array('mutenotitext','value','tojid','shownotification'));
            $tojid    = $this->request->data['tojid'];
            $value    = $this->request->data['value'];
            $mutenoti = $this->request->data['mutenotitext'];
            $shownotification = $this->request->data['shownotification'];


            if(!in_array($mutenoti, ['hours','week','year']) ){

                $this->errorMessage(300,'Field mutenotitext wrong.');

            }

            if(!in_array($value, [1,8]) ){

                $this->errorMessage(300,'Field value wrong.');

            }
            if(!in_array($shownotification, [1,0]) ){

                $this->errorMessage(300,'shownotification should be 0 or 1.');

            }

            $this->loadModel('Mutenotifications');

             $toUser = $this->getUserByUserID($tojid);

             if(empty($toUser)){
                $this->errorMessage(300,'This user not exist.');
             }

            $toid = $toUser['id'];
            $res = $this->Mutenotifications->find()
            ->select(['id'])
            ->where(['toid'=>$toid,'fromid'=>$uid])
            ->first();

             $response = [];
             $settime = time();
             $settime = strtotime('+'.$value.$mutenoti,$settime);

            if(empty($res)){

                  $notification = $this->Mutenotifications->newEntity();
                  $notification->fromid = $uid;
                  $notification->toid = $toid;
                  $notification->ismuteoff = 0;
                  $notification->mutenoti = $mutenoti;
                  $notification->value = $value;
                  $notification->settime = $settime;
                  $notification->shownotification = $shownotification;
                  $notification->updated = time();


                  if($this->Mutenotifications->save($notification))
                  {
                     $response["code"]    = 0;
                     $response["error"]   = false;
                     $response["message"] = 'Mute notification successfully.';

                  }else{

                    $this->errorMessage(300,'There is something wrong.');

                  }
            }
            else{

                  $id = $res['id'];
                  $notification = $this->Mutenotifications->get($id);
                  $notification->ismuteoff = 0;
                  $notification->mutenoti = $mutenoti;
                  $notification->value = $value;
                  $notification->settime = $settime;
                  $notification->shownotification = $shownotification;
                  $notification->updated = time();

                  if($this->Mutenotifications->save($notification))
                  {
                     $response["code"]    = 0;
                     $response["error"]   = false;
                     $response["message"] = 'Mute notification successfully.';

                  }else{

                    $this->errorMessage(300,'There is something wrong.');

                  }
            }

            $this->echoRespnse($response);
     }



     public function getMuteNotificaton()
    {

            $uid = $this->Auth->user('id');

            $this->validateRequired(array('tojid'));
            $tojid    = $this->request->data['tojid'];

             $this->loadModel('Mutenotifications');

             $toUser = $this->getUserByUserID($tojid);

             if(empty($toUser)){
                $this->errorMessage(300,'This user not exist.');
             }

            $toid = $toUser['id'];
            $ctime = time();
            $res = $this->Mutenotifications->find()
            ->select(['id','settime','mutenoti','value','shownotification'])
            ->where(['toid'=>$toid,'fromid'=>$uid, 'settime >' => $ctime, 'ismuteoff'=>0])
            ->first();

             $response = [];


            if($res){

                     $endtime = $res['settime'];
                     $dt['ismute']  = true;
                     $dt['shownotification']  = $res['shownotification'];
                     $dt['untilmute']   = $res['settime'];
                     $dt['value']       = $res['value'];
                     $dt['mutenoti']    = $res['mutenoti'];
                     //$dt['pendingmute'] = $this->get_time_difference($ctime,$endtime);

                     $response["code"]    = 0;
                     $response["error"]   = false;
                     $response["message"] = 'Mutenotifications details';
                     $response["data"]    = $this->removenull($dt) ;

            }
            else{

                     $dt['ismute']  = false;
                     $dt['shownotification']  = '';
                     $dt['untilmute']   = '';
                     $dt['value']       = '';
                     $dt['mutenoti']    = '';
                    // $dt['pendingmute'] = ;

                     $response["code"]    = 0;
                     $response["error"]   = false;
                     $response["message"] = 'Mutenotifications details';
                     $response["data"]    =  $dt ;

            }

            $this->echoRespnse($response);
     }

      public function muteNotificatonOff()
    {

            $uid = $this->Auth->user('id');

            $this->validateRequired(array('tojid'));
            $tojid    = $this->request->data['tojid'];

             $this->loadModel('Mutenotifications');

             $toUser = $this->getUserByUserID($tojid);

             if(empty($toUser)){
                $this->errorMessage(300,'This user not exist.');
             }

            $toid = $toUser['id'];
            $res = $this->Mutenotifications->find()
            ->select(['id'])
            ->where(['toid'=>$toid,'fromid'=>$uid])
            ->first();

             $response = [];


            if(!empty($res)){

                  $id = $res['id'];
                  $notification = $this->Mutenotifications->get($id);

                  $notification->ismuteoff = 1 ;
                  $notification->shownotification = 0;

                  $notification->updated = time();

                  if($this->Mutenotifications->save($notification))
                  {
                     $response["code"]    = 0;
                     $response["error"]   = false;
                     $response["message"] = 'Mute notification off successfully.';

                  }else{

                    $this->errorMessage(300,'There is something wrong 2.');

                  }
            }else{

                  $this->errorMessage(300,'There is something wrong 1.');
            }

            $this->echoRespnse($response);
     }


      public function setBackup()
    {

         $uid = $this->Auth->user('id');
         $response = [];

        $this->validateRequired(array('email','bkptype'));

        $email   = $this->request->data['email'];
        $bkptype = $this->request->data['bkptype'];
        $file    = $this->request->data['file'];


        if(!in_array($bkptype, ['history','contacts']))
         {
            $this->errorMessage(300,'Filed bkptype value wrong.');
         }


        $dir = $this->image_dir."backup/";
        $url = $this->image_link."backup/";

              if(isset($file['name']) && !empty($file['name']))
              {

               $filetype = explode('/',$file['type']);
               if(!in_array($filetype[1], ['zip'])){

                $this->errorMessage(300,'Wrong file format.');

               }


                // Upload Image code
                $img = explode('.',$file["name"]);
                $ext = end($img);
                //$imgname = time().'_'.$file["name"];
                $filename = $email.'_'.$bkptype.'.'.$filetype[1];
                $target_file = $dir . $filename;

                if (move_uploaded_file($file["tmp_name"], $target_file)){

                    $response["code"]  = 0;
                    $response["error"] = false;
                    $response["message"] = 'Backup successfully done.';

                }else{

                    $this->errorMessage(300,'There is some thing wrong.');

                }
            }else{

                $this->errorMessage(300,'There is some thing wrong.backup file is not set');
            }


            $this->echoRespnse($response);
    }


     public function getBackup()
    {

         $uid = $this->Auth->user('id');
         $response = [];

        $this->validateRequired(array('email','bkptype'));

        $email   = $this->request->data['email'];
        $bkptype = $this->request->data['bkptype'];

        if(!in_array($bkptype, ['history','contacts']))
         {
            $this->errorMessage(300,'Filed bkptype value wrong.');
         }


        $dir = $this->image_dir."backup/";
        $url = $this->image_link."backup/";


            $filename = $email.'_'.$bkptype.'.zip';
            $target_file = $dir . $filename;

            if(file_exists($target_file)){

                    $response["code"]  = 0;
                    $response["error"] = false;
                    $response["message"] = 'Backup '.$bkptype;
                    $response["data"]  =  array(
                                            'backuptype'=> $bkptype,
                                            'backupurl' => $url.$filename
                                            );

            }else{

                    $response["code"]  = 0;
                    $response["error"] = false;
                    $response["message"] = 'There is no '.$bkptype.' backup.';
                    $response["data"]  =  array(
                                            'backuptype'=> '',
                                            'backupurl' => ''
                                            );

            }

            $this->echoRespnse($response);
    }



     public function statusPrivacy()
    {
        $uid = $this->Auth->user('id') ;

        $this->validateRequired(array('contacts','statusprivacy'));

        $contacts = $this->request->data['contacts'];
        $statusprivacy = $this->request->data['statusprivacy'];

        $contacts = str_replace("\n",'', $contacts);
        $contacts = str_replace("\r",'', $contacts);
        $contacts = str_replace(" ",'', $contacts);
        $contacts = str_replace("  ",'', $contacts);


        if(!in_array($statusprivacy,['mycontacts','except','sharewith']))
        {

            $this->errorMessage(300,'Field statusprivacy value not valid.');

        }

        $response = array();

        $this->loadModel('Statusprivacy');

        $res = $this->Statusprivacy->find()
        ->select(['id'])
        ->where(['uid'=>$uid])
        ->first();

       if(empty($res)){

               $statuspri = $this->Statusprivacy->newEntity();
               $statuspri->uid = $uid;
               $statuspri->statusprivacy = $statusprivacy;
               $statuspri->contacts = $contacts;

               if($this->Statusprivacy->save($statuspri)){


                    $response["code"] = 0;
                    $response["error"] = false;
                    $response["message"] = 'Status privacy saved successfully.';

               }else{

                 $this->errorMessage(300,'There is some thing wrong.');

               }

       }else{
               $id = $res['id'];
               $statuspri = $this->Statusprivacy->get($id);
               $statuspri->statusprivacy = $statusprivacy;
               $statuspri->contacts = $contacts;
               if($this->Statusprivacy->save($statuspri)){

                    $response["code"] = 0;
                    $response["error"] = false;
                    $response["message"] = 'Status privacy saved successfully.';

               }else{

                    $this->errorMessage(300,'There is some thing wrong.');

               }

       }



        $this->echoRespnse($response);
    }


     public function getStatusPrivacy()
    {
        $uid = $this->Auth->user('id') ;

        $response = array();

        $this->loadModel('Statusprivacy');

        $res = $this->Statusprivacy->find()
        ->select(['id','statusprivacy','contacts'])
        ->where(['uid'=>$uid])
        ->first();

       if(!empty($res)){

                $st['statusprivacy'] = $res['statusprivacy'];
                $st['contacts'] = $res['contacts'];
                if($res['statusprivacy'] == 'mycontacts'){
                  $st['contacts'] = '';
                }
                $response["code"] = 0;
                $response["error"] = false;
                $response["message"] = 'Status privacy status.';
                $response["data"] = $st;

       }else{

                $st['statusprivacy'] = 'mycontacts';
                $st['contacts'] = '';
                $response["code"] = 0;
                $response["error"] = false;
                $response["message"] = 'Status privacy status';
                $response["data"] = $st;
       }



        $this->echoRespnse($response);
    }


    public function blockUser()
    {
        $uid = $this->Auth->user('id') ;

        $this->validateRequired(array('phone'));
        $phone = $this->request->data['phone'];

        $response = array();

        $this->loadModel('Blockusertbls');

         $userres = $this->getUserByUid($uid);

         $fromuserid = $userres['userid'];

        $row = $this->Blockusertbls->find()
            ->select(['id'])
            ->where(['fromuser' => $fromuserid])
            ->first();

       if(!empty($row)){

               $id = $row['id'];

               $item = $this->Blockusertbls->get($id);

               if($this->Blockusertbls->delete($item)){

                    $response["code"] = 0;
                    $response["error"] = false;
                    $response["message"] = 'User unblocked.';

               }else{

                 $this->errorMessage(300,'There is some thing wrong.');

               }

       }else{

               $blockuser = $this->Blockusertbls->newEntity();

               $blockuser->fromuser = $fromuserid;
               $blockuser->touser = $phone;
               $blockuser->created = time();

               if($this->Blockusertbls->save($blockuser)){

                    $response["code"] = 0;
                    $response["error"] = false;
                    $response["message"] = 'User blocked.';

                }else{

                    $this->errorMessage(300,'There is some thing wrong.');

                }

       }



        $this->echoRespnse($response);
    }



     public function getBlockUser()
    {
        $uid = $this->Auth->user('id') ;

        $this->validateRequired(array('phone'));
        $phone = $this->request->data['phone'];

        $response = array();

        $this->loadModel('Blockusertbls');

         $userres = $this->getUserByUid($uid);

         $fromuserid = $userres['userid'];

        $row = $this->Blockusertbls->find()
            ->select(['id'])
            ->where(['fromuser' => $fromuserid])
            ->first();

       if(!empty($row)){

                $response["code"] = 0;
                $response["error"] = false;
                $response["message"] = 'User blocked';
                $response["data"] = ['isuserblocked'=>true];

       }else{
                $response["code"] = 0;
                $response["error"] = false;
                $response["message"] = 'User not blocked';
                $response["data"] = ['isuserblocked'=>false];
       }


        $this->echoRespnse($response);
    }





}
