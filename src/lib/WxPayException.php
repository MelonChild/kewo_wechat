<?php
namespace Kewo\Wechat\lib;

use Exception;
/**
 * 
 * 微信支付API异常类
 * @author widyhu
 *
 */
class WxPayException extends Exception {
	/**
     * 报告这个异常。
     *
     * @return void
     */
    public function report()
    {
    }

    /**
     * 将异常处理
     *
     */
    public function render()
    {
		$data['errno'] = $this->getMessage();
		$data['message'] = $this->getCode();
        return $data;
    }
}
