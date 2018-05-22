<?php

namespace library\base;
use library\util\Utils;
use library\util\Sign;

class ServiceBase {

    public function callEthersan($url, $data){
        log::debug($url);
        $response = RpcClient::callHttpGet("http_etherscan", $url, $data);
        $newTxs = json_decode($response, 1);
        if($newTxs['status'] != 1){
            Log::warning("san block info faild".serialize($newTxs));
            return false;
        }
        return $newTxs;
    }

    public function callGeth($data){
        $id = Log::genTraceID();
        $data['jsonrpc'] = "2.0";
        $data['id'] = $id;
        $ret = self::httpBinaryCall("http_geth", $data);
        if(!$ret){
            Log::warning("call geth faild".serialize($ret));
            return false;
        }

        //curl -X POST --data '{"jsonrpc":"2.0","method":"eth_getBlockTransactionCountByNumber","params":["latest111111"],"id":2}'
        //{"jsonrpc":"2.0","id":2,"error":{"code":-32602,"message":"invalid argument 0: hex string without 0x prefix"}}
        //{"jsonrpc":"2.0","id":2,"result":"0x1"}
        $retArr = json_decode($ret, 1);
        if(isset($retArr['error']) &&  $retArr['error']['code'] != 0){
            Log::warning("push data faild, errno not 0".serialize($retArr));
            return false;
        }

        if($retArr['jsonrpc'] != "2.0" || $retArr['id'] != $id){
            Log::warning("response not match id:${id}, response:".serialize($retArr));
            return false;
        }

        if($retArr['result'] == null){
            Log::warning("response result is null, pls check input params");
            return false;
        }
        return $retArr;
    }


    private function httpBinaryCall($service, $data){
        $ch = curl_init();
        $host = RpcCore::getHosts($service);
        $serviceExtral = RpcCore::getServiceExtral($service);
        $port = isset($serviceExtral['port']) ? $serviceExtral['port'] : 80;
        $connTimeOut = RpcCore::getServiceConnTimeout($service);
        $exeTimeOut = RpcCore::getServiceExeTimeout($service);
        if($connTimeOut > 0) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $connTimeOut);
        }

        if($exeTimeOut > 0) {
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $exeTimeOut);
        }
        $url = "http://${host}:${port}";
        curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/json'));

        curl_setopt($ch, CURLOPT_URL,            $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($ch, CURLOPT_POST,           1 );
        curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($data));
        $result=curl_exec ($ch);
        $errno = curl_errno($ch);
        if($errno != CURLE_OK) {
            Log::warning("curl faild, $url, errno:${errno}");
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return $result;
    }


    public function getEnv(){
        if(defined('ENVIRON')){
            $env = ENVIRON;
        }else{
            $env = "develop";
        }
        return $env;
    }
}
