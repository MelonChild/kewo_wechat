<?php
namespace Kewo\lib;
// require_once "WxPay.Exception.php";
// require_once "WxPay.Config.Interface.php";
use Kewo\lib\WxPayException;

/**
 * 
 * 接口访问类，包含所有微信支付API列表的封装，类中方法为static方法，
 * 每个接口有默认超时时间（除提交被扫支付为10s，上报超时时间为1s外，其他均为6s）
 * @author widyhu
 *
 */
class WxPayApi
{
	
	/**
     * 
     * 传值验证
     */
    public function verifyData($value,$field,$err,$msg){
        
        if(!isset($value[$field])) {
            throw new WxPayException($msg,$err);
        }
	}

	/**
	 * 
	 * 格式化参数格式化成url参数
	 * 
	 */
	public function ToUrlParams($datas)
	{
		$buff = "";
		foreach ($datas as $k => $v)
		{
			if($k != "sign" && $v != "" && !is_array($v)){
				$buff .= $k . "=" . $v . "&";
			}
		}
		
		$buff = trim($buff, "&");
		return $buff;
	}
		
	/**
     * 
     * 设置签名
	 * 
     */
	public function setSign($datas,$key,$type="MD5"){
		//签名步骤一：按字典序排序参数
		ksort($datas);
		$string = $this->ToUrlParams($datas);
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=".$key;
		//签名步骤三：MD5加密或者HMAC-SHA256
		if($type == "MD5"){
			$string = md5($string);
		} else if($type == "HMAC-SHA256") {
			$string = hash_hmac("sha256",$string ,$key);
		} else {
			throw new WxPayException('签名方式不支持','1006');
		}
		$result = strtoupper($string);
		return $result;
	}

	/**
	 * 输出xml字符
	 * @throws WxPayException
	**/
	public function ToXml($datas)
	{
		if(!is_array($datas) || count($datas) <= 0)
		{
    		throw new WxPayException("数组数据异常！");
    	}
    	
    	$xml = "<xml>";
    	foreach ($datas as $key=>$val)
    	{
    		if (is_numeric($val)){
    			$xml.="<".$key.">".$val."</".$key.">";
    		}else{
    			$xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
    		}
        }
        $xml.="</xml>";
        return $xml; 
	}

	/**
	 * 以post方式提交xml到对应的接口url
	 * 
	 * @param WxPayConfigInterface $config  配置对象
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	private static function postXmlCurl($config, $xml, $url, $useCert = false, $second = 30)
	{	
		$ch = curl_init();
		$curlVersion = curl_version();
		$ua = "WXPaySDK/3.0.9 (".PHP_OS.") PHP/".PHP_VERSION." CURL/".$curlVersion['version']." "
		.$config->GetMerchantId();

		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);

		$proxyHost = "0.0.0.0";
		$proxyPort = 0;
		$config->GetProxy($proxyHost, $proxyPort);
		//如果有配置代理这里就设置代理
		if($proxyHost != "0.0.0.0" && $proxyPort != 0){
			curl_setopt($ch,CURLOPT_PROXY, $proxyHost);
			curl_setopt($ch,CURLOPT_PROXYPORT, $proxyPort);
		}
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
		curl_setopt($ch,CURLOPT_USERAGENT, $ua); 
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if($useCert == true){
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			//证书文件请放入服务器的非web目录下
			$sslCertPath = "";
			$sslKeyPath = "";
			$config->GetSSLCertPath($sslCertPath, $sslKeyPath);
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, $sslCertPath);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, $sslKeyPath);
		}
		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		//运行curl
		$data = curl_exec($ch);
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
			throw new WxPayException("curl出错，错误码:$error",5703);
		}
	}
	
	/**
	 * 获取毫秒级别的时间戳
	 */
	private static function getMillisecond()
	{
		//获取毫秒的时间戳
		$time = explode ( " ", microtime () );
		$time = $time[1] . ($time[0] * 1000);
		$time2 = explode( ".", $time );
		$time = $time2[0];
		return $time;
	}

    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
	public function FromXml($xml)
	{	
		if(!$xml){
			throw new WxPayException("xml数据异常！",5704);
		}
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
		return $values;
	}

	/**
     * 将xml转为array
     * @param wechat $config  配置对象
     * @param string $xml
     * @throws WxPayException
     */
	public function resultInit($xml,$key)
	{	
		$values = $this->FromXml($xml);
		//失败则直接返回失败
		if($values['return_code'] != 'SUCCESS') {
			foreach ($values as $key => $value) {
				#除了return_code和return_msg之外其他的参数存在，则报错
				if($key != "return_code" && $key != "return_msg"){
					throw new WxPayException("输入数据存在异常！",5705);
					return false;
				}
			}
			return $values;
		}
		$this->resultCheckSign($values,$key);
        return $values;
	}

	/**
	 * @param array $values  配置对象
	 * @param string $key  支付key
	 * 检测签名
	 */
	public function resultCheckSign($values,$key)
	{
		if(!$this->IsSignSet($values)){
			throw new WxPayException("签名错误！",5706);
		}
		
		$sign = $this->MakeSign($values,$key);
		if($values['sign'] == $sign){
			//签名正确
			return true;
		}
		throw new WxPayException("签名错误！",5706);
	}
		
	/**
	 * 判断签名，详见签名生成算法是否存在
	 * @return true 或 false
	 */
	public function IsSignSet($values)
	{
		return array_key_exists('sign', $values);
	}

	/**
	 * 结果验证生成签名
	 * @param array $values  配置对象
	 * @param string $key  支付key
	 * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
	 */
	public function MakeSign($values,$key)
	{
		//签名步骤一：按字典序排序参数
		ksort($values);
		$string = $this->ToUrlParams($values);
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=".$key;
		//签名步骤三：MD5加密或者HMAC-SHA256
		if(strlen($values['sign']) <= 32){
			//如果签名小于等于32个,则使用md5验证
			$string = md5($string);
		} else {
			//是用sha256校验
			$string = hash_hmac("sha256",$string ,$key);
		}
		//签名步骤四：所有字符转为大写
		$result = strtoupper($string);
		return $result;
	}
	
	
	/**
	 * 
	 * 统一下单，WxPayUnifiedOrder中out_trade_no、body、total_fee、trade_type必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param array $input
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public function unifiedOrder($input,$obj,$timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/pay/unifiedorder";

		//检测必填参数
		$this->verifyData($input,'out_trade_no',1005,'缺少统一支付接口必填参数out_trade_no！');
		$this->verifyData($input,'trade_type',1005,'缺少统一支付接口必填参数trade_type！');
		$this->verifyData($input,'body',1005,'商品描述为空');
		$this->verifyData($input,'total_fee',1005,'商品价格为空');
		$this->verifyData($input,'notify_url',1005,'异步通知链接为空');
		
		//关联参数
		if($input['trade_type'] == "JSAPI"){
			$this->verifyData($input,'openid',1005,'统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！');
		}
		if($input['trade_type'] == "NATIVE"){
			$this->verifyData($input,'product_id',1005,'统一支付接口中，缺少必填参数product_id！trade_type为JSAPI时，product_id为必填参数！');
		}
		
		//随机字符串
		$input['nonce_str'] = self::getNonceStr();
		//签名
		$input['sign'] = self::setSign($input,$obj->getPayKey(),$obj->getSignType());
		
		//签名
		$xml = self::ToXml($input);
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($obj, $xml, $url, false, $timeOut);
		$result = self::resultInit($response,$obj->getPayKey());
		$result['pre_nonce_str'] = $input['nonce_str']; 
		return $result;
	}
	
	/**
	 * 
	 * 查询订单，WxPayOrderQuery中out_trade_no、transaction_id至少填一个
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayOrderQuery $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function orderQuery($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/pay/orderquery";
		//检测必填参数
		if(!$inputObj->IsOut_trade_noSet() && !$inputObj->IsTransaction_idSet()) {
			throw new WxPayException("订单查询接口中，out_trade_no、transaction_id至少填一个！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
	/**
	 * 
	 * 关闭订单，WxPayCloseOrder中out_trade_no必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayCloseOrder $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function closeOrder($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/pay/closeorder";
		//检测必填参数
		if(!$inputObj->IsOut_trade_noSet()) {
			throw new WxPayException("订单查询接口中，out_trade_no必填！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}

	/**
	 * 
	 * 申请退款，WxPayRefund中out_trade_no、transaction_id至少填一个且
	 * out_refund_no、total_fee、refund_fee、op_user_id为必填参数
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayRefund $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function refund($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
		//检测必填参数
		if(!$inputObj->IsOut_trade_noSet() && !$inputObj->IsTransaction_idSet()) {
			throw new WxPayException("退款申请接口中，out_trade_no、transaction_id至少填一个！");
		}else if(!$inputObj->IsOut_refund_noSet()){
			throw new WxPayException("退款申请接口中，缺少必填参数out_refund_no！");
		}else if(!$inputObj->IsTotal_feeSet()){
			throw new WxPayException("退款申请接口中，缺少必填参数total_fee！");
		}else if(!$inputObj->IsRefund_feeSet()){
			throw new WxPayException("退款申请接口中，缺少必填参数refund_fee！");
		}else if(!$inputObj->IsOp_user_idSet()){
			throw new WxPayException("退款申请接口中，缺少必填参数op_user_id！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, true, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
	/**
	 * 
	 * 查询退款
	 * 提交退款申请后，通过调用该接口查询退款状态。退款有一定延时，
	 * 用零钱支付的退款20分钟内到账，银行卡支付的退款3个工作日后重新查询退款状态。
	 * WxPayRefundQuery中out_refund_no、out_trade_no、transaction_id、refund_id四个参数必填一个
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayRefundQuery $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function refundQuery($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/pay/refundquery";
		//检测必填参数
		if(!$inputObj->IsOut_refund_noSet() &&
			!$inputObj->IsOut_trade_noSet() &&
			!$inputObj->IsTransaction_idSet() &&
			!$inputObj->IsRefund_idSet()) {
			throw new WxPayException("退款查询接口中，out_refund_no、out_trade_no、transaction_id、refund_id四个参数必填一个！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
	/**
	 * 下载对账单，WxPayDownloadBill中bill_date为必填参数
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayDownloadBill $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function downloadBill($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/pay/downloadbill";
		//检测必填参数
		if(!$inputObj->IsBill_dateSet()) {
			throw new WxPayException("对账单接口中，缺少必填参数bill_date！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		if(substr($response, 0 , 5) == "<xml>"){
			return "";
		}
		return $response;
	}
	
	/**
	 * 提交被扫支付API
	 * 收银员使用扫码设备读取微信用户刷卡授权码以后，二维码或条码信息传送至商户收银台，
	 * 由商户收银台或者商户后台调用该接口发起支付。
	 * WxPayWxPayMicroPay中body、out_trade_no、total_fee、auth_code参数必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayWxPayMicroPay $inputObj
	 * @param int $timeOut
	 */
	public static function micropay($config, $inputObj, $timeOut = 10)
	{
		$url = "https://api.mch.weixin.qq.com/pay/micropay";
		//检测必填参数
		if(!$inputObj->IsBodySet()) {
			throw new WxPayException("提交被扫支付API接口中，缺少必填参数body！");
		} else if(!$inputObj->IsOut_trade_noSet()) {
			throw new WxPayException("提交被扫支付API接口中，缺少必填参数out_trade_no！");
		} else if(!$inputObj->IsTotal_feeSet()) {
			throw new WxPayException("提交被扫支付API接口中，缺少必填参数total_fee！");
		} else if(!$inputObj->IsAuth_codeSet()) {
			throw new WxPayException("提交被扫支付API接口中，缺少必填参数auth_code！");
		}
		
		$inputObj->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//终端ip
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
	/**
	 * 
	 * 撤销订单API接口，WxPayReverse中参数out_trade_no和transaction_id必须填写一个
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayReverse $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 */
	public static function reverse($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/secapi/pay/reverse";
		//检测必填参数
		if(!$inputObj->IsOut_trade_noSet() && !$inputObj->IsTransaction_idSet()) {
			throw new WxPayException("撤销订单API接口中，参数out_trade_no和transaction_id必须填写一个！");
		}
		
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, true, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}

	
	/**
	 * 
	 * 生成二维码规则,模式一生成支付二维码
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayBizPayUrl $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function bizpayurl($config, $inputObj, $timeOut = 6)
	{
		if(!$inputObj->IsProduct_idSet()){
			throw new WxPayException("生成二维码，缺少必填参数product_id！");
		}

		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetTime_stamp(time());//时间戳	 
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		
		return $inputObj->GetValues();
	}
	
	/**
	 * 
	 * 转换短链接
	 * 该接口主要用于扫码原生支付模式一中的二维码链接转成短链接(weixin://wxpay/s/XXXXXX)，
	 * 减小二维码数据量，提升扫描速度和精确度。
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayShortUrl $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function shorturl($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/tools/shorturl";
		//检测必填参数
		if(!$inputObj->IsLong_urlSet()) {
			throw new WxPayException("需要转换的URL，签名用原串，传输需URL encode！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
 	/**
 	 * 
 	 * 支付结果通用通知
 	 * @param function $callback
 	 * 直接回调函数使用方法: notify(you_function);
 	 * 回调类成员函数方法:notify(array($this, you_function));
 	 * $callback  原型为：function function_name($data){}
 	 */
	public static function notify($config, $callback, &$msg)
	{
		if (!isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
			# 如果没有数据，直接返回失败
			return false;
		}

		//如果返回成功则验证签名
		try {
			//获取通知的数据
			$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
			$result = WxPayNotifyResults::Init($config, $xml);
		} catch (WxPayException $e){
			$msg = $e->errorMessage();
			return false;
		}
		
		return call_user_func($callback, $result);
	}
	
	/**
	 * 
	 * 产生随机字符串，不长于32位
	 * @param int $length
	 * @return 产生的随机字符串
	 */
	public static function getNonceStr($length = 32) 
	{
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {  
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		} 
		return $str;
	}
	
	/**
	 * 直接输出xml
	 * @param string $xml
	 */
	public static function replyNotify($xml)
	{
		echo $xml;
	}


	
}

