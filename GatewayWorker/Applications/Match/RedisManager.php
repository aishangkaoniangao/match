<?php
class RedisManager{
	/**
	* hostname
	*
	* @var string
	*/
	const REDISHOSTNAME = "127.0.0.1";
	/**
	* port
	*
	* @var int
	*/
	const REDISPORT = 6379;
	/**
	* timeout
	*
	* @var int
	*/
	const REDISTIMEOUT = 0;
	/**
	* password
	*
	* @var string
	*/
	const REDISPASSWORD = '';
	/**
	* 类单例数组
	*
	* @var array
	*/
	private static $instance = array();
	/**
	* redis连接句柄
	*
	* @var object
	*/
	private $redis;
	/**
	* 私有化构造函数,防止类外实例化
	*
	* @param int $dbnumber
	*/
	private function __construct (){
        $this->redis = new Redis();
		@$this->redis->connect(self::REDISHOSTNAME, self::REDISPORT, self::REDISTIMEOUT);
	}
	/**
	* 私有化克隆函数，防止类外克隆对象
	*/
	private function __clone (){

	}
	/**
	* 获取类单例
	*
	* @return object
	*/
	public static function getRedisInstance (){
		if (!(self::$instance instanceof self)) {
			self::$instance = new self();
	    }
	    return self::$instance;
	}
	/**
	* 获取redis的连接实例
	*
	* @return object
	*/
	public function getRedisConnect (){
		$error = error_get_last();
		if($error != null && !empty($error['message'])){
			return false;
		}else{
			return $this->redis;
		}
	}
	/**
	* 关闭单例时做清理工作
	*/
	public function __destruct (){
		self::$instance->redis->close();
		self::$instance = null;
	}
}
?>
