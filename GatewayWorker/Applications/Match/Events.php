<?php
use \GatewayWorker\Lib\Gateway;
use Workerman\Worker;
require 'Match.php';
class Events
{

    const GAMENAME = 'Match'; //游戏名称
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * 
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id) {
        // debug
        $log = array(
            'time' => date("Y-m-d H:i:s"),
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'],
            'REMOTE_PORT' => $_SERVER['REMOTE_PORT'],
            'GATEWAY_ADDR' => $_SERVER['GATEWAY_ADDR'],
            'GATEWAY_PORT' => $_SERVER['GATEWAY_PORT'],
            'client_id' => $client_id,
        );
        file_put_contents("/data/logs/workerman/access.log", json_encode($log)."\n",FILE_APPEND);
        $new_message = array('type'=>'welcome', 'time'=>time());
        // 向当前client_id发送数据 
        Gateway::sendToCurrentClient(json_encode($new_message));
    }
    
   /**
    * 当客户端发来消息时触发
    * @param int $client_id 连接id
    * @param mixed $message 具体消息
    */
    public static function onMessage($client_id, $message) {
        $message_data = json_decode($message, true);
        if(!$message_data)
        {
            return ;
        }

        //匹配动作
        switch ($message_data['type']) {
            case 'match':
                $number = isset($message_data['number']) ? intval($message_data['number']) : 2;
                $actid = isset($message_data['actid']) ? $message_data['actid'] : 0;
                $key = self::GAMENAME.'_'.$actid;
                //默认至少两个匹配
                $number = $number > 1 ? $number : 2;
                Gateway::setSession($client_id, array('actid'=>$actid, 'roomNum'=>$number));
                
                $match = new Match($key,$number);
                $ret = $match->match($client_id);
                if($ret['code'] == 0){
                    $roomId = $ret['roomId'];
                    $client_id_array = $ret['client_array'];
                    $data = [
                        'number' => $number,
                        'roomId' => $ret['roomId'],
                    ];

                    $userType = 0;
                    foreach ($ret['client_array'] as $key => $value) {
                        $data['userType'] = $userType;
                        $msg = ['code'=>0,'msg'=>'matchSuccess','data'=>$data];
                        Gateway::updateSession($value, ['userType'=>$userType]);
                        Gateway::sendToClient($value,json_encode($msg));
                        $userType++;
                    }
                }elseif($ret['code'] == 1 || $ret['code'] == 2){
                    //匹配中
                    $data = [
                        'number' => $number,
                    ];
                    $msg = ['code'=>1,'msg'=>'matching','data'=>$data];
                    Gateway::sendToClient($client_id,json_encode($msg));
                }else{
                    //匹配失败
                    $msg = ['code'=>-1,'msg'=>'matchFailed','data'=>[]];
                    Gateway::sendToClient($client_id,json_encode($msg));
                }
                break;
            case 'quit':
                if(isset($_SESSION['actid']) && isset($_SESSION['roomNum'])){
                    $actid = $_SESSION['actid'];
                    $number = $_SESSION['roomNum'];
                    $key = self::GAMENAME.'_'.$actid;
                    //退出匹配
                    $match = new Match($key,$number);
                    $res = $match->logout($client_id);
                    if($res){
                        $msg = ['code'=>0,'msg'=>'quitSuccess'];
                    }else{
                        $msg = ['code'=>-1,'msg'=>'unmatch'];
                    }
                    Gateway::sendToClient($client_id,json_encode($msg));
                }else{
                    $msg = ['code'=>-1,'msg'=>'unmatch'];
                    Gateway::sendToClient($client_id,json_encode($msg));
                }
                break;
        }
        return;
    }
   
    /**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
    public static function onClose($client_id) {
        $actid = isset($_SESSION['actid']) ? $_SESSION['actid'] : 0;
        $number = isset($_SESSION['roomNum']) ? $_SESSION['roomNum'] : 2;
        $key = self::GAMENAME.'_'.$actid;

        //断开连接后退出匹配
        $match = new Match($key,$number);
        $match->logout($client_id);
    }
}