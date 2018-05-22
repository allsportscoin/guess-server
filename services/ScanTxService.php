<?php
namespace services;

use library\base\ServiceBase;
use library\define\Constant;
use library\util\Utils;
use services\Match as MatchService;
use services\Gamble as GambleService;
use services\PrizeRecord as PrizeRecordService;
use services\AddressToMatch as AddressToMatchService;
use services\InchargeRecord as InchargeRecordService;

class ScanTxService extends ServiceBase
{

    public static function scanTx(){
        $apikey = Conf::getConf("setting.apikey");
        $token = Conf::getConf("setting.token");
        $blockNumber = self::_apcu_fetch("blockNumber");
        if($blockNumber == false){
            Log::warning("block number error");
            $blockNumber = 5560470;
        }
        $data = array(
            "module" => "account",
            "action" => "txlist",
            "startblock" => $blockNumber,
            "endblock" => "latest",
            "sort" => "desc",
            "apikey" => $apikey,
            "address" => $token,
        );
        $newTxs = self::callEthersan("api", $data);
        if(!$newTxs){
            Log::warning("san block info faild");
            return false;
        }
        $result = $newTxs['result'];
        $currBlock = $blockNumber;
        foreach($result as $tx){
            if($tx['isError'] != 0){
                Log::warning("tx is faild in ethersacn");
                continue;
            }
            $hash = $tx['hash'];
            $input = $tx['input'];
            if($input == "0x"){
                Log::warning("trans eth not token, ignore~");
                continue;
            }
            $currBlock = $tx['blockNumber'] > $currBlock ? $tx['blockNumber'] : $currBlock;
            $ret = InchargeRecordService::getRecordByTxid($hash);
            if(false !== $ret) {
                Log::warning("${hash} exists");
                continue;
            }
            Log::debug("ENV=" . ENVIRON . " " . Constant::NODE_BIN . " scripts/detailTx_soc.js ${input}");
            $result = system("ENV=" . ENVIRON . " " . Constant::NODE_BIN . " scripts/detailTx_soc.js ${input}");
            if(strlen($result) == 0){
                Log::warning("get tx info faild");
                continue;
            }
            $txData = json_decode($result, 1);
            if($txData['status'] === "error"){
                Log::warning("get tx info faild");
                continue;
            }
            $value = floatval($txData['value']) / Constant::DECIMALS_18;

            try{
                $matchInfo = AddressToMatchService::getMatchByAddress($txData['to']);
                if ($matchInfo === false) {
                    Log::warning('');
                    continue;
                }
                $matchInfo = $matchInfo[0];
                $matchId = $matchInfo['matchid'];
                $guessResult = $matchInfo['bet_label'];
                $ret = InchargeRecordService::insert($hash, $tx['from'], $txData['to'], $value, $matchId);
                $ret = GambleService::insert($guessTxid='', $matchId, $tx['from'], $value, $guessResult);
                self::_apcu_store("blockNumber", $currBlock);
                $homeNum = $drawNum = $awayNum = 0;
                if ($guessResult == 0) {
                    $homeNum = $value;
                } else if ($guessResult == 1) {
                    $drawNum = $value;
                } else if ($guessResult == 2) {
                    $awayNum = $value;
                }
            }catch(Exception $e){
                Log::warning("error happend ");
            }
        }
    }

    public static function createTokenTx($address, $value, $from){
        if (strpos($pk, '0x') === 0) {
            $pk = substr($pk, 2);
        }
        $value = strval($value) . Constant::APPEND_DECIMALS_18;
        $strtx = system("ENV=" . ENVIRON . " " . Constant::NODE_BIN . " scripts/createTx_soc.js ${address} ${value} ${from}");
        $arrtx = json_decode($strtx, 1);
        if($arrtx['status'] == "error"){
            Log::warning("errror:{$arrtx['errmsg']}");
            return false;;
        }
        $txid = $arrtx['txhash'];
        return $txid;
    }

    public static function createGuessTx($mid, $guesser, $type, $value){
        $result = system("ENV=" . ENVIRON . " " . Constant::NODE_BIN . " scripts/createTx_guess.js ${mid} ${guesser} ${type} $value");
        log::warning("add guess $result");
        $arrResult = json_decode($result, 1);

        if($arrResult['status'] == "error"){
            Log::warning("creat addguess faild");
            return false;
        }
        return $arrResult;
    }

    public static function _apcu_fetch($name){
        $resultFile = "/tmp/soc/$name";
        return file_get_contents($resultFile);
    }

    public static function _apcu_store($name, $value){
        $resultFile = "/tmp/soc/$name";
        file_put_contents($resultFile, $value);
    }
}
