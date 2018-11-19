<?php
namespace Api\Controller;
use App\Controller\AppController as BaseController;
use Cake\Network\Response;
use Cake\Event\Event;
use Cake\Controller\Component\RequestHandlerComponent;
use App\Model\Table\UsersTable;
use PhpParser\Node\Stmt\Global_;
use Cake\Routing\Router;
use Firebase\JWT\JWT;

use Cake\Utility\Security;

class AppController extends BaseController
{
      public $user_id;

      public function initialize()
      {

         parent::initialize();
         $this->loadComponent('RequestHandler');
        
        // $this->Auth->allow();

        // try {
             $this->request->allowMethod(['post','get']);
       /*  }catch (\Exception $e) {
             $this->errorMessage('Method not allowed.');
         }*/

      }

    public function beforeFilter(Event $event) {
       // if (in_array($this->request->action, ['actions_you want to disable'])) {
            $this->eventManager()->off($this->Csrf);
      //  }
    }
      
      public function beforeRender(Event $event)
	  {   if (!in_array($this->request->action, ['aboutus','support','privacypolicy','termsandconditions','pages'])) {
              $this->RequestHandler->renderAs($this, 'json');
              $this->response->type('application/json');
              $this->set('_serialize', true);
        }
	  }


	  public function validateRequired($required_fields = array())
	  {
		   $error = false;
           $error_fields = "";
           $request_params = array();
           $request_params = $this->request->data ;
           
           foreach ($required_fields as $field) {
				if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
					$error = true;
					$error_fields .= $field . ', ';
				}
           }
           
           if ($error) {
		        $response = array();
		        $response["code"] = 10;
                $response["error"] = true;
                $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
                $this->echoRespnse($response);
           } else { return true; }

	  }


       public function get_time_difference($start, $end) {
    
            $uts['start']  =  $start;
            $uts['end']    =  $end;

            if ($uts['start']!=false && $uts['end']!=false) {
                if ($uts['end'] >= $uts['start']) {
                    $diff = $uts['end'] - $uts['start'];
                    
                    $months = intval((floor($diff/60/60/24/30))); 
                     if($months > 0){ $diff = $diff-($months*(60*60*24)); }
                    $days = intval((floor($diff/60/60/24)));
                     if($days > 0){ $diff = $diff-($days*(60*60*24)); }
                    $hours = intval((floor($diff/60/60)));
                     if($hours > 0){ $diff = $diff-($hours*(60*60)); }
                    $minutes = intval((floor($diff/60)));
                     if($minutes > 0){ $diff = $diff-($minutes*(60)); }
                    $seconds = intval($diff);            
                    return(array('months'=>$months,'days'=>$days, 'hours'=>$hours, 'minutes'=>$minutes, 'seconds'=>$seconds));
                } else {
                    return(false);
                }
            } else {
                return(false);
            }
            return(false);
      }

      public function checkidentify(){
       
            

            $headerdata = getallheaders();
            
           if(isset($headerdata[Authorization]) && !empty($headerdata[Authorization]))
            {

                try{  
                  $secretkey = Security::salt(); 
                  $decoded = JWT::decode($headerdata[Authorization], $secretkey, array('HS256'));
                  $data = json_decode(json_encode($decoded),true);

                  $this->loadModel('Users');
                  $checkusr = $this->Users->find()
                  ->select(['id', 'userid','phone'])
                  ->where(['id' => $data['id'],'token'=>$headerdata[Authorization]])
                  ->first(); 
                  if($checkusr){
                       
                       return $checkusr;

                  }else{
                      
                     $this->errorMessage(401,'Un-Authorized access'); 

                  }
                }catch (\Exception $e) {

                   $this->errorMessage(401,'Un-Authorized access');
       
                }
           } 
           else
           { 
                $this->errorMessage(401,'Un-Authorized access');
           }
      }
	  
	  public function validateEmail($email = null)
	  { 
		   if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$response = array();
				$response["code"] = 11;
				$response["error"] = true;
				$response["message"] = 'Email address is not valid';
				$this->echoRespnse($response);
		    }
         
	  }
	  
	  public function verifyEmail($email = null)
	  { 
		   $this->loadModel('Users');
		   $userData = $this->Users->findByEmail($email)->toArray();
		   if (!empty($userData)) {
				$response = array();
				$response["code"] = 2;
                $response["error"] = true;
                $response["message"] = 'Account already exists with email '.$email.'.';
				$this->echoRespnse($response);
		   }
      }
      
      public function verifyUsername($username = null)
	  { 
		   $this->loadModel('Users');
		   $userData = $this->Users->findByUsername($username)->toArray();
		   if (!empty($userData)) {
				$response = array();
				$response["code"] = 2;
                $response["error"] = true;
                $response["message"] = 'Account already exists with username '.$username.'.';
				$this->echoRespnse($response);
		   }
      }

      public function verifyPhone($phone = null)
      {  
           $this->loadModel('Users');
           $userData = $this->Users->findByPhone($phone)->toArray();
           if (!empty($userData)) {   
                $response = array();
                $response["code"] = 2;
                $response["error"] = true;
                $response["message"] = 'Account already exists with Phone Number '.$phone.'.';
                $this->echoRespnse($response);
           }
      }
      
      public function validateUsername($username = null)
	  { 
		   if (!ctype_alnum($username)) {
				$response = array();
				$response["code"] = 2;
                $response["error"] = true;
                $response["message"] = 'Username is not valid .';
				$this->echoRespnse($response);
		   }
      }

    public function getUserAllData($userid = null)
    {
        $user = array();
        $user = $this->getUserByUserID($userid);
        $data['userid'] = $user->userid;
        $data['phone'] = $user->phone;
        $user = array();
        $user = $this->getProfileByUserID($userid);
        $data['name'] = $user->name;
        if($user->image != '')
            $data['image'] = Router::url('/webroot/uploads/profile/', true).$user->image;
        else
            $data['image'] = '';
        $user = array();
        $user = $this->getUserSettings($userid);
        $data['settings']['enable_archive'] = $user->enable_archive;
        $data['settings']['sound'] = $user->sound;
        $data['settings']['desktop_alerts'] = $user->desktop_alerts;
        return $data;
    }

    public function getUserById($userid =null)
    {
	    $this->loadModel('Users');
		$userData = $this->Users->get($userid,['fields'=>['id', 'userid','phone']])->toArray();
		return $userData;
	}

    public function getStatusImagesById($id =null)
    {
        $this->loadModel('StatusImages');
        $row = $this->StatusImages->find()
            ->select(['id', 'userid'])
            ->where(['id' => $id])
            ->first();
        if(!empty($row))
            return true;
        return false;
    }

     public function getStatusImagesByIdAndUser($id =null,$uid)
    {
        $this->loadModel('StatusImages');
        $row = $this->StatusImages->find()
            ->select(['id', 'userid'])
            ->where(['id' => $id,'uid' => $uid])
            ->first();
        if(!empty($row))
            return true;
        return false;
    }

    public function getUserSettings($userid=null){
        $this->loadModel('Usersettings');
        $row = $this->Usersettings->find()
            ->select(['id', 'userid','uid','enable_archive','sound','desktop_alerts','readreceipts','showsecuritynoti'])
            ->where(['uid' => $userid])
            ->first();
        if(!empty($row)) {
            return $row;
        }
    }

    public function getStatusImageByUserid($id=null){
        $this->loadModel('StatusImages');
        $stime = time()-(24*60*60);
        $stime = $stime*1000;
        $row = $this->StatusImages->find()
            ->select(['id', 'userid','uid','type','textdata','image','caption','status','view_status','created'])
            ->where(['uid' => $id,'created >'=>$stime])
            ->toArray();
        if(!empty($row)) {
            return $row;
        }
    }

    public function getStatusAll($uid=null){

        $this->loadModel('StatusImages');
        $stime = time()-(24*60*60);
        $stime = $stime*1000;

        $row = $this->StatusImages->find()
            ->select(['id', 'userid','uid','type','textdata','image','caption','status','view_status','created'])
            ->where(['uid !='=> $uid , 'created >'=>$stime])
            ->toArray();
        if(!empty($row)) {
            return $row;
        }

    }

    public function getStatusAllByPrivacy($uid=null){

        $this->loadModel('StatusImages');
        $stime = time()-(24*60*60);
        $stime = $stime*1000;
      
       $row = $this->StatusImages->find('all',['fields'=>['id', 'userid','uid','type','textdata','image','caption'],'conditions'=>['uid'=> $uid , 'created >'=>$stime]])->hydrate(false)->toArray();
        if(!empty($row)) {
            return $row;
        }
        
    }

     public function getStatusAllByPrivacyOuter($uid=null){

        $this->loadModel('StatusImages');
        $stime = time()-(24*60*60);
        $stime = $stime*1000;

        $row = $this->StatusImages->find()
            ->select(['id', 'userid','uid'])
            ->where(['uid !='=> $uid , 'created >'=>$stime])
            ->group(['uid'])
            ->toArray();
        if(!empty($row)) {
            return $row;
        }
        
    }

    public function checkStatusPrivacy($suid,$uid){
         
         $resUser = $this->getUserById($uid); 
         $userid = $resUser['userid']; 


         $this->loadModel('Mycontacts'); 
         $this->loadModel('Statusprivacy'); 
         

          
         $resMycontacts = $this->Mycontacts->find('all',['conditions'=>['uid'=>$suid,'FIND_IN_SET('.$uid.',Mycontacts.contacts)']])->hydrate(false)->toArray();

         if(empty($resMycontacts)){
            return false;
         }

          $resArr = $this->getStatusPrivacyByUid($suid);                

          if($resArr[statusprivacy] == 'except'){
            
             $resMycontacts1 = $this->Statusprivacy->find('all',['fields'=>['id','uid','statusprivacy'],'conditions'=>['uid'=>$suid,'FIND_IN_SET('.$userid.',Statusprivacy.contacts)']])->hydrate(false)->first();
                  
            if($resMycontacts1){
                return false;
            }
          }

          if($resArr[statusprivacy] == 'sharewith'){              
              $resMycontacts2 = $this->Statusprivacy->find('all',['conditions'=>['uid'=>$suid,'FIND_IN_SET('.$userid.',Statusprivacy.contacts)']])->hydrate(false)->first();

                if(empty($resMycontacts2)){
                    return false;
                }             
          }

        return true;  

    }

   public function getStatusPrivacyByUid($uid)
   {

        $this->loadModel('Statusprivacy');
       
        $res = $this->Statusprivacy->find()
        ->select(['id','statusprivacy','contacts'])
        ->where(['uid'=>$uid])
        ->first();
       if(!empty($res)){

                $st['statusprivacy'] = $res['statusprivacy'];
                $st['contacts']      = $res['contacts'];
                if($res['statusprivacy'] == 'mycontacts'){
                  $st['contacts'] = '';
                }                   
               return $st;

       }else{
                $st['statusprivacy'] = 'mycontacts';
                $st['contacts'] = '';
                return $st;
       }
    }

    public function getImageByName($img=null){
        $this->loadModel('ChatImages');
        $row = $this->ChatImages->find()
            ->select(['id', 'image','caption','created'])
            ->where(['image' => $img])
            ->first();
        if(!empty($row)) {
            return $row;
        }
    }

    public function getUserByUserID($id=null){
        $this->loadModel('Users');
        $row = $this->Users->find()
            ->select(['id', 'userid','phone'])
            ->where(['userid' => $id])
            ->first();
        if(!empty($row)) {
            return $row;
        }
    }

     public function getUserByUid($id=null){
        $this->loadModel('Users');
        $row = $this->Users->find()
            ->select(['id', 'userid','phone'])
            ->where(['id' => $id])
            ->first();
        if(!empty($row)) {
            return $row;
        }
    }

    public function getUserProfileByUserID($id=null){
        $this->loadModel('Userprofiles');
        $row = $this->Userprofiles->find()
            ->select(['id', 'userid','uid','name','image'])
            ->where(['uid' => $id])
            ->first();
        if(!empty($row)) {
            return $row;
        }
    }

    public function getUserByPhone($phone=null){
        $this->loadModel('Users');
        $row = $this->Users->find()
            ->select(['id', 'userid','country_id','phone','created'])
            //->where(['phone' => $phone, 'status' => 1])
            ->where(['userid' => $phone, 'status' => 1])
            ->first();
        if(!empty($row)) {
            return $row;
        }
    }

    public function checkPhoneExist($phone=null){
        $this->loadModel('Users');
        $row = $this->Users->find()
            ->select(['id', 'userid','country_id','phone','created'])
            ->where(['phone' => $phone])
            ->first();
        if(!empty($row)) {
            return $row;
        }
    }

    public function getProfileByUserID($id=null){
        $this->loadModel('Userprofiles');
        $row = $this->Userprofiles->find()
            ->select(['id', 'userid','name','image'])
            ->where(['userid' => $id])
            ->first();
        if(!empty($row)) {
            return $row;
        }
    }

    public function getCountryCodeById($country_id =null)
    {
        $this->loadModel('Countries');
        $countryData = $this->Countries->get($country_id,['fields'=>['id', 'name','dialcode','iso2','iso3']])->toArray();
        return $countryData;
    }

    public function authenticateUser()
    {
        $this->Auth->config([
            'authenticate' => [
                'Basic' => [
                    'fields' => ['username' => 'pin_id', 'password' => 'password'],
                ],
            ],
        ]);

        $response = array();
        $this->loadModel('Users');
        $user = $this->Auth->identify();

        if (!empty($user)) {

            if ($user['status'] == '1') {
                if(!is_null($user['expire_date'])){
                    $dt = time();
                    if($user['expire_date'] < $dt){
                        $response["code"] = 31;
                        $response["error"] = true;
                        $response["message"] = 'Account is expired , Please contact to admin !!.';
                        $this->echoRespnse($response);
                    }
                }
                  $this->Auth->setUser($user);
                  return true;

            } else {

                $response["code"] = 14;
                $response["error"] = true;
                $response["message"] = 'Account is not active , Please contact to admin !!.';
                $this->echoRespnse($response);

            }

        } else {
            $response["code"] = 14;
            $response["error"] = true;
            $response["message"] = 'Incorrect Username or Password !!.';
            $this->echoRespnse($response);
        }

    }

    public function authenticateUserDeleteApi($code)
    {
        $this->Auth->config([
            'authenticate' => [
                'Basic' => [
                    'fields' => ['username' => 'pin_id', 'password' => 'password'],
                ],
            ],
        ]);

        $response = array();
        $this->loadModel('Users');

        $user = $this->Auth->identify();

        if (!empty($user)) {

            if ($user['status'] == '1') {
                if(!is_null($user['expire_date'])){
                    $dt = time();
                    if($user['expire_date'] < $dt){
                        $response["code"] = 31;
                        $response["error"] = true;
                        $response["message"] = 'Account is expired , Please contact to admin !!.';
                        $this->echoRespnse($response);
                    }
                }
                $this->Auth->setUser($user);
                return true;

            } else {

                $response["code"] = 14;
                $response["error"] = true;
                $response["message"] = 'Account is not active , Please contact to admin !!.';
                $this->echoRespnse($response);

            }

        } else {
            $this->errorMessage($code,'User not exist');
        }
    }

    public function getCreatedByName($id=null){
        $this->loadModel('Users');
        $row = $this->Users->find()
            ->select(['id','name','logo'])
            ->where(['id' => $id])
            ->first();
        if(!empty($row)) {
            return $row;
        }
    }

	public function authenticateUser123()
    {
		$this->loadModel('Users');
        // pr($user['id']); die;
		$this->request->data['username'] = env('PHP_AUTH_USER');
        $this->request->data['password'] = env('PHP_AUTH_PW');
        // echo $h = env('HTTP_AUTHORIZATION'); exit;
        $user = $this->Auth->identify();
        if(!empty($user))
        { 
		    return $user;
		} 
		else
		{ 
            $response = array();
			      $response["code"] = 6;
            $response["error"] = true;
            $response["message"] = 'Un-Authorized access .';
			      $this->echoRespnse($response);
  	    }
	}

    public function tokenAuthontication()
    {
        $response = array();
        $this->loadModel('Users');
        try{
            $user = $this->Auth->identify();
        }catch (\Exception $e) {
           $this->errorMessage(14,'Token signature verification failed');
        }

        if(!empty($user)){

            if($user['status'] == '1')
            {
                return $user['id'];

            } else {

                $response["code"] = 14;
                $response["error"] = true;
                $response["message"] = 'Account is not active , Please contact to admin !!.';
                $this->echoRespnse($response);

            }

        } else {
            $response["code"] = 14;
            $response["error"] = true;
            $response["message"] = 'User token not valid !!.';
            $this->echoRespnse($response);
        }

    }

    public function errorMessage($code,$msg)
    {
        $response["code"] = $code;
        $response["error"] = true;
        $response["message"] = $msg;
        $this->echoRespnse($response);
    }

    public function checkToken($token,$user_id)
    {
        $this->loadModel('ApiRequests');
        $isExists = $this->ApiRequests->find('all', [
            'conditions' => ['ApiRequests.token' => $token,'ApiRequests.user_id' => $user_id],
            'order' => ['created' => 'DESC']
        ])->first();

            $cur_time = time()-60*5;
            $this->ApiRequests->deleteAll([ 'created <=' => $cur_time]);

        if (!empty($isExists)) {

            if(time()-$isExists['created'] > 180){

                $response = array();
                $response["code"] = 14;
                $response["error"] = true;
                $response["message"] = 'Token expired, please try again.';
                $this->echoRespnse($response);

            }else{

                return true;

            }
        } else {
            $response = array();
            $response["code"] = 14;
            $response["error"] = true;
            $response["message"] = 'Invalid token pass.';
            $this->echoRespnse($response);
        }

    }

	public function echoRespnse($response)
	{
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
	}

    public function generateRandomCode($length)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        for ($i = 0; $i <= $length; $i++) {
            $num = rand(0, strlen($characters) - 1);
            $output[] = $characters[$num];
        }
        return implode($output);
    }

    public function sendPushNotificationApi($deviceToken, $title = 'Encchatapp', $notification_msg = '',$device_type='',$ntype='',$to='',$from='',$rstatus='') {

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
            'message'=> $notification_msg,
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

        $fields = array
        (
            'priority' => "high",
           // 'notification' => array("title" => $title, "body" => $notification_msg),
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
       // pr($result); exit;

        return true;
    }

    public function removenull($assocarray = array())
    {

        if (!empty($assocarray)) {
            foreach ($assocarray as $key => $value) {
                if (is_null($value)) {
                    $assocarray[$key] = "";
                }
            }
        }
        return $assocarray;

    }
}
