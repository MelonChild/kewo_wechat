<?php
/*
 *  Copyright (c) 2014 The CCP project authors. All Rights Reserved.
 *
 */
namespace Kewo;

class Wechat
{
    private $appid; //
    private $appsecret;
    private $app;
    private $role=3;
    private $enabeLog = true; //日志开关。可填值：true、
    private $Filename = "./kewolog.txt"; //日志文件
    private $Handle;
    private $batch; //时间戳
    private $baseUrl = "http://ke.test.hw2006.org/manageapi/v1/"; //路由请求基础路由

    public function __construct($appid='',$appsecret='',$app='',$role='')
    {
        $this->batch = date("YmdHis");
        $this->appid = $appid;
        $this->appsecret = $appsecret;
        $this->app = $app;
        $this->role = $role;
        $this->Handle = fopen($this->Filename, 'a');
        $_SESSION['expire_in'] = 0;
    }

    /**
     * 设置应用ID
     *
     * @param AppId 应用ID
     */
    public function setAppId($appid)
    {
        $this->appid = $appid;
    }

    /**
     * 设置应用密匙
     *
     * @param Appsecret 应用密匙
     */
    public function setAppSecret($appsecret)
    {
        $this->appsecret = $appsecret;
    }
    
    /**
     * 设置接口所属应用
     *
     * @param app 应用id
     */
    public function setApp($app)
    {
        $this->app = $app;
    }
    
    /**
     * 设置用户角色，登录时候用
     *
     * @param role 用户角色
     */
    public function setRole($role)
    {
        $this->role = $role;
    }
    
    /**
     * 设置日志开关
     *
     * @param enabeLog 应用密匙
     */
    public function setEnabeLog($enabeLog)
    {
        $this->enabeLog = $enabeLog;
    }

    
    /**
     * 主帐号鉴权
     */
    public function accAuth()
    {

        if ($this->appsecret == "") {
            $data = new \stdClass();
            $data->errcode = '1003';
            $data->errmsg = '应用密钥为空';
            return $data;
        }
        if ($this->appid == "") {
            $data = new \stdClass();
            $data->errcode = '1002';
            $data->errmsg = 'appid为空';
            return $data;
        }
        if ($this->app == "") {
            $data = new \stdClass();
            $data->errcode = '1004';
            $data->errmsg = '应用ID为空';
            return $data;
        }
    }

    /**
     * 打印日志
     *
     * @param log 日志内容
     */
    public function showlog($log)
    {
        if ($this->enabeLog) {
            fwrite($this->Handle, $log . "\n");
        }
    }

    /**
     * 发起HTTPS请求
     *
     * @param url 请求路径
     * @param data 发送数据
     * @param header 请求头部信息
     * @param post 请求方式  默认为1 post请求   0为get 请求
     */
    public function curl_post($url, $data=[], $header, $post = 1)
    {
        //初始化curl
        $ch = curl_init();
        //参数设置
        if ($post) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            $url = $url.'?'.http_build_query($data);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, $post);
        

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        //连接失败
        if ($result == false) {
            $result = "{\"errcode\":\"1001\",\"errmsg\":\"网络错误\"}";
        }

        curl_close($ch);
        return $result;
    }

    /**
     * 发起HTTPS请求
     *
     * @param url 请求路径
     * @param path 文件相对路径
     */
    public function curl_post_file($url, $path)
    {
        //初始化curl
        $ch = curl_init();
        if (class_exists('\CURLFile')) {
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
            $data = array('media' => new \CURLFile(realpath($path))); //>=5.5
        } else {
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
            }
            $data = array('media' => '@' . realpath($path)); //<=5.5
        }
        //参数设置
        $res = curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        //连接失败
        if ($result == false) {
            $result = "{\"errcode\":\"1001\",\"errmsg\":\"网络错误\"}";
        }

        curl_close($ch);
        return $result;
    }

    /**
     * 账号密码登录
     */
    public function loginByAccount($username,$password,$role=3,$version=1)
    {
        //鉴权信息验证，对必选参数进行判空。
        $auth = $this->accAuth();
        if ($auth != "") {
            return $auth;
        }
        //生成token

        //检测用户角色
        $role || $role = $this->role;
        $this->showlog("login by account, request datetime = " . date('y/m/d h:i') . "\n");

        // 生成请求URL
        $url = $this->baseUrl."loginIn";

        // 生成包头
        $header = array("Accept:application/json", "Content-Type:application/json;charset=utf-8");

        //数据
        $time = time();
        $data['appid']=$this->appid;
        $data['version']=$version;
        $data['token']=md5(md5($this->appsecret).$time).$time;

        $data['username']=$username;
        $data['password']=md5($password);
        $data['role']=$role;
        $data['app']=$this->app;
        $data['type']=1;

        // 发送请求
        $result = $this->curl_post($url, $data, $header, 0);
        $this->showlog("response body = " . $result . "\r\n");
        $datas = json_decode($result, true);

        return $datas;
    }

}
