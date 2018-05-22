<?php
namespace services;

use library\base\ServiceBase;
use library\define\Constant;
use library\define\CacheKey;
use library\util\Utils;
use services\ScanTxService;
use services\AddressToMatch as AddressToMatchService;
use services\Gamble as GambleService;
use services\PrizeRecord as PrizeRecordService;
use services\InchargeRecord as InchargeRecordService;

class Match extends ServiceBase {

    public static function updateMatchOdds($matchId, $homeNum, $drawNum, $awayNum, $totalUser = 1) {
        $key = CacheKey::MATCH_COUNT . $matchId;
        $objRedis = RpcClient::getRedis("redis_soc_bet");

        $homeSoc = $objRedis->hIncrBy($key, 'home_soc', $homeNum);
        $drawSoc = $objRedis->hIncrBy($key, 'draw_soc', $drawNum);
        $awaySoc = $objRedis->hIncrBy($key, 'away_soc', $awayNum);
        $totalSoc = $objRedis->hIncrBy($key, 'total_soc', $homeNum + $drawNum + $awayNum);
        $totalUser = $objRedis->hIncrBy($key, 'total_user', $totalUser);
        $homeSp = round(1.0 / (1.0 * $homeSoc / $totalSoc), 2);
        $drawSp = round(1.0 / (1.0 * $drawSoc / $totalSoc), 2);
        $awaySp = round(1.0 / (1.0 * $awaySoc / $totalSoc), 2);
        $objRedis->hset($key, 'home_sp', $homeSp);
        $objRedis->hset($key, 'draw_sp', $drawSp);
        $objRedis->hset($key, 'away_sp', $awaySp);
    }

    public static function rebuildMatchOdds($matchId, $homeNum, $drawNum, $awayNum, $totalUser = 1) {
        $key = CacheKey::MATCH_COUNT . $matchId;
        $objRedis = RpcClient::getRedis("redis_soc_bet");

        $totalSoc = $homeNum + $drawNum + $awayNum;
        $objRedis->hset($key, 'home_soc', $homeNum);
        $objRedis->hset($key, 'draw_soc', $drawNum);
        $objRedis->hset($key, 'away_soc', $awayNum);
        $objRedis->hset($key, 'total_soc', $totalSoc);
        $objRedis->hset($key, 'total_user', $totalUser);
        $homeSp = round(1.0 / (1.0 * $homeNum / $totalSoc), 2);
        $drawSp = round(1.0 / (1.0 * $drawNum / $totalSoc), 2);
        $awaySp = round(1.0 / (1.0 * $awayNum / $totalSoc), 2);
        $objRedis->hset($key, 'home_sp', $homeSp);
        $objRedis->hset($key, 'draw_sp', $drawSp);
        $objRedis->hset($key, 'away_sp', $awaySp);
    }

    public static function openPrize($matchId, $status, $result = false) {
        if ($status == 5) {
            $odds = 1;
        } else {
            $arrOdds = self::getOddsByMatchId($matchId);
            if ($result == 0) {
                $odds = round($arrOdds['home_sp'], 2);
            } else if ($result == 1) {
                $odds = round($arrOdds['draw_sp'], 2);
            } else {
                $odds = round($arrOdds['away_sp'], 2);
            }
        }
        //check odds right
        $arrWin = array();
        $arrRecord = GambleService::getAllByMatchid($matchId);
        $homeNum = $drawNum = $awayNum = $totalUser = 0;
        foreach ($arrRecord as $item) {
            $totalUser++;
            if ($item['result'] == 0) {
                $homeNum += $item['value'];
            } else if ($item['result'] == 1) {
                $drawNum += $item['value'];
            } else {
                $awayNum += $item['value'];
            }
            if ($status == 4 && $item['result'] !== $item['match_result']) {
                continue;
            }
            $value = floatval($item['value']);
            $address = strtolower($item['address']);
            if (isset($arrWin[$address])) {
                $arrWin[$address] += $value;
            } else {
                $arrWin[$address] = $value;
            }
        }
        if ($status != 5) {
            $totalSoc = $homeNum + $drawNum + $awayNum;
            $homeSp = round(1.0 / (1.0 * $homeNum / $totalSoc), 2);
            $drawSp = round(1.0 / (1.0 * $drawNum / $totalSoc), 2);
            $awaySp = round(1.0 / (1.0 * $awayNum / $totalSoc), 2);
            if ($arrOdds['home_sp'] != $homeSp || $arrOdds['draw_sp'] != $drawSp ||
                $arrOdds['away_sp'] != $awaySp || $arrOdds['total_soc'] != $totalSoc) {
                self::rebuildMatchOdds($matchId, $homeNum, $drawNum, $awayNum, $totalUser);
                if ($result == 0) {
                    $odds = $homeSp;
                } else if ($result == 1) {
                    $odds = $drawSp;
                } else {
                    $odds = $awaySp;
                }
            }
        }
        foreach ($arrWin as $addr => $val) {
            $num = round($odds * $val);
            $txid = '';
            $insertId = PrizeRecordService::insert($matchId, $addr, $num, $odds, $txid);
            if (false === $insertId) {
                Log::warning("First,insert prize record error,address[$addr],matchid[$matchId],num[$num],odds[$odds]");
                $insertId = PrizeRecordService::insert($matchId, $addr, $num, $odds, $txid);
                if (false === $insertId) {
                    Log::warning("Second,insert prize record error,address[$addr],matchid[$matchId],num[$num],odds[$odds]");
                    continue;
                }
            }
        }
        return true;
    }

}




/* vim: set ts=4 sw=4 sts=4 tw=2000 et: */
