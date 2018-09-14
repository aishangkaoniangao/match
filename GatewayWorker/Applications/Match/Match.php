<?php
use \GatewayWorker\Lib\Gateway;
require_once 'RedisManager.php';

class Match {

	private $redis;
	public $key;
	public $number;

	/**
	 * 运行redis初始化参数
	 * @param  [type]     $key    [description]
	 * @param  integer    $number [description]
	 */
	function __construct($key,$number = 2){
		$instance = RedisManager::getRedisInstance();
		$this->redis = $instance->getRedisConnect();
		$this->key = $key.'_'.$number;
		$this->number = $number;
	}

	/**
	 *用户匹配
	 * @param  [type]     $client_id [description]
	 * @return [type]                [description]
	 */
	public function match($client_id){
		$client_id_array = array();
		$msg = array(
			'code' => '',
			'msg' => '',
			'roomId' => '',
			'number' => '',
			'client_array' => ''
		);
		$matnum = $this->number-1;
		$res = true;

		//redis连接失败
		if($this->redis === false){
			$msg['code'] = -1;
			$msg['msg'] = '匹配失败';
		}else{
			$len = $this->redis->llen($this->key);
			if($len < $matnum){
				$this->redis->rpush($this->key,$client_id);
				$msg['code'] = 1;
				$msg['msg'] = '匹配中';
			}else{
				$client_id_array[] = $client_id;
				for($i=1;$i<=$matnum;$i++){
					$ret = $this->redis->lpop($this->key);
					//id已被取出，重新放回list池
					if(!$ret){
						foreach ($client_id_array as $key => $value) {
							$this->redis->lpush($this->key,$value);
						}
						$msg['code'] = 2;
						$msg['msg'] = '匹配失败,重新匹配';
						$res = false;
						break;
					}
					$client_id_array[] = $ret;
				}

				if($res){
					//生成房间号
					$roomId = 'SERVER_'.time().'_'.mt_rand(0,1000);
					$msg = [
						'code' => 0,
						'msg' => '匹配成功',
						'roomId' => $roomId,
						'number' => $this->number,
						'client_array' => $client_id_array,
					];
				}
			}
		}

		return $msg;
	}

	/**
	 * @登出调用
	 * @param  [type]     $client_id [description]
	 * @return [type]                [description]
	 */
	public function logout($client_id){
		if($this->redis !== false){
			return $this->redis->lrem($this->key,$client_id,0);
		}
	}

	public function look($client_id){
		return $this->redis->lrange($this->key,0,50);
	}	
}