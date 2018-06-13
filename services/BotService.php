<?php
namespace services;

use library\base\ServiceBase;
use Log;
use RpcClient;
use Conf;

class BotService extends ServiceBase {

    public static function getAllMatchInfos() {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "127.0.0.1/bet");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($result,true);
        return $result['data']['list'];
    }

    public static function processMessage($message) {
        // process incoming message
        $chat_id = $message['chat']['id'];
        $from = $message['from']['id'];
        date_default_timezone_set('UTC');

        $objRedis = RpcClient::getRedis("redis_soc_bet");
        $match_cache = "guessbot:from_$from:chat_$chat_id:match";
        $playing_cache = "guessbot:from_$from:chat_$chat_id:plaring";

        if (isset($message['text'])) {
            // incoming text message
            $text = $message['text'];
            if ($text=="complete") {
                $objRedis->delete($match_cache);
                $objRedis->delete($playing_cache);

                $keyboard_array = array(
                    array("/start"),
                );
                $keyBoardParam = array(
                    "chat_id"=>$chat_id,
                    "text"=>"Welcome to SOC GUESS Bot. 
                    
To start guessing, use the /start command.",
                    "parse_mode"=>"Markdown",
                    'reply_markup' => array(
                        "keyboard" => $keyboard_array,
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true
                    )
                );

                self::apiRequest("sendMessage", $keyBoardParam);
            } else if ($text === "/start") {
                $objRedis->delete($match_cache);
                $objRedis->delete($playing_cache);

                $keyboard_array = array(
                    array("soccer"),
                    array("cancel")
                );
                $keyBoardParam = array(
                    "chat_id"=>$chat_id,
                    "text"=>"Welcome to SOC GUESS Bot. 
                    
Which sport category are you interest in? Choose an option to continue.",
                    "parse_mode"=>"Markdown",
                    'reply_markup' => array(
                        "keyboard" => $keyboard_array,
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true
                    )
                );

                self::apiRequest("sendMessage", $keyBoardParam);
            } else if ($text == "soccer"){
                $objRedis->delete($match_cache);
                $objRedis->delete($playing_cache);

                $match_list = self::getAllMatchInfos();

                $keyboard_array = array();
                foreach ($match_list as $item) {
                    $time = date('Y-m-d H:i:s',$item['begin_time']);
                    $teamA = $item['teamA'];
                    $teamB = $item['teamB'];
                    $competition = $item['competition'];
                    $keyboard_array[] = array("$competition - $teamA vs $teamB");
                }
                $keyboard_array[] = array("cancel");
                if (count($keyboard_array)) {
                    $keyBoardParam = array(
                        "chat_id"=>$chat_id,
                        "text"=>"Select what to guess on",
                        "parse_mode"=>"Markdown",
                        'reply_markup' => array(
                            "keyboard" => $keyboard_array,
                            'one_time_keyboard' => true,
                            'resize_keyboard' => true
                        )
                    );
                } else {
                    $keyBoardParam = array(
                        "chat_id"=>$chat_id,
                        "text"=>"no game right now.",
                        "parse_mode"=>"Markdown",
                        'reply_markup' => array(
                            "keyboard" => array(["/start"]),
                            'one_time_keyboard' => true,
                            'resize_keyboard' => true
                        )
                    );
                }

                self::apiRequest("sendMessage", $keyBoardParam);
            } else if (strstr($text," vs ")) {
                //analyse text of type: Friendlies - Austria vs Brazil
                $match_list = self::getAllMatchInfos();

                $objRedis->delete($playing_cache);

                $competition = explode(" - ",$text)[0];
                $teamA = explode(" vs ",explode(" - ",$text)[1])[0];
                $teamB = explode(" vs ",$text)[1];

                $game_infos = array();
                foreach ($match_list as $item) {
                    if ($item['competition']==$competition &&
                        $teamA=$item['teamA'] && $teamB==$item['teamB']) {
                        $game_infos = $item['games'];

                        $objRedis->setex($match_cache,600,$item['matchid']);
                        break;
                    }
                }
                $keyboard_array = array();

                foreach ($game_infos as $item) {
                    $playing_method = '';
                    if ($item['game_id']==1) {
                        $playing_method = 'Full time result - starting price';
                    } else if ($item['game_id']==2) {
                        $playing_method = 'Asian Handicap - starting price';
                    } else if ($item['game_id']==3) {
                        $playing_method = 'Full time result - fixed odds';
                    } else if ($item['game_id']==4) {
                        $playing_method = 'Asian Handicap - fixed odds';
                    }
                    $keyboard_array[] = array("$playing_method");
                }
                $keyboard_array[] = array("cancel");
                $keyBoardParam = array(
                    "chat_id"=>$chat_id,
                    "text"=>"Guess on something !",
                    "parse_mode"=>"Markdown",
                    'reply_markup' => array(
                        "keyboard" => $keyboard_array,
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true
                    )
                );
                self::apiRequest("sendMessage", $keyBoardParam);
            } else if (strstr($text,'Full time result') || strstr($text,'Asian Handicap')) {
                //analyse text of type：Asian Handicap - starting price
                if ($objRedis->exists($match_cache)) {
                    $match_id = $objRedis->get($match_cache);
                } else {
                    self::errorProcess($chat_id);
                    return;
                }

                $playing_method = $text;

                $match_list = self::getAllMatchInfos();
                $game_infos = array();
                foreach ($match_list as $item) {
                    if ($item['matchid']==$match_id ) {
                        $teamA = $item['teamA'];
                        $teamB = $item['teamB'];
                        $game_infos = $item['games'];
                        break;
                    }
                }
                $gameid = 1;

                if ($playing_method=='Full time result - starting price') {
                    $gameid = 1;
                } else if ($playing_method=='Asian Handicap - starting price') {
                    $gameid = 2;
                } else if ($playing_method=='Full time result - fixed odds') {
                    $gameid = 3;
                } else if ($playing_method=='Asian Handicap - fixed odds') {
                    $gameid = 4;
                }

                $objRedis->setex($playing_cache,600,$gameid);

                $odds_info = array();
                foreach ($game_infos as $game_info) {
                    if ($gameid == $game_info['game_id']) {
                        $odds_info = $game_info;
                        break;
                    }
                }
                if ($text =='Asian Handicap - starting price') {
                    $keyboard_array = array(
                        //Russia -1.25 | SP: 2.03
                        array("$teamA ".$odds_info['sp_draw']." | SP: ".$odds_info['sp_home']),
                        array("$teamB +".(-$odds_info['sp_draw'])." | SP: ".$odds_info['sp_away']),
                        array("cancel")
                    );
                } else if ($text =='Asian Handicap - fixed odds') {
                    $keyboard_array = array(
                        //Russia -1.25 | SP: 2.03
                        array("$teamA ".$odds_info['sp_draw']." | Odds: ".$odds_info['sp_home']),
                        array("$teamB +".(-$odds_info['sp_draw'])." | Odds: ".$odds_info['sp_away']),
                        array("cancel")
                    );
                } else if ($text == 'Full time result - starting price') {
                    $keyboard_array = array(
                        //Russia | SP:1.37
                        array("$teamA | SP: ".$odds_info['sp_home']),
                        array("Draw | SP: ".$odds_info['sp_draw']),
                        array("$teamB | SP: ".$odds_info['sp_away']),
                        array("cancel")
                    );
                } else {
                    $keyboard_array = array(
                        //Russia | SP:1.37
                        array("$teamA | Odds: ".$odds_info['sp_home']),
                        array("Draw | Odds: ".$odds_info['sp_draw']),
                        array("$teamB | Odds: ".$odds_info['sp_away']),
                        array("cancel")
                    );
                }

                $keyBoardParam = array(
                    "chat_id"=>$chat_id,
                    "text"=>"Make a selection",
                    "parse_mode"=>"Markdown",
                    'reply_markup' => array(
                        "keyboard" => $keyboard_array,
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true
                    )
                );
                self::apiRequest("sendMessage", $keyBoardParam);
            } else if (strstr($text,"SP") || strstr($text,'Odds')) {
                //analyse text of type: Russia | SP:1.37 OR Russia -1.25 | SP: 2.03
                if ($objRedis->exists($match_cache) && $objRedis->exists($playing_cache) ) {
                    $match_id = $objRedis->get($match_cache);
                    $gameid = $objRedis->get($playing_cache);
                } else {
                    $objRedis->delete($match_cache);
                    $objRedis->delete($playing_cache);

                    self::errorProcess($chat_id);
                    return;
                }

                $match_list = self::getAllMatchInfos();
                $game_infos = array();

                foreach ($match_list as $item) {
                    if ($match_id==$item['matchid']) {
                        if (strstr($text,$item['teamA'])) {
                            $sp = 'home';
                        } else if (strstr($text,$item['teamB'])) {
                            $sp = 'away';
                        } else {
                            $sp = "draw";
                        }
                        $competition = $item['competition'];
                        $teamA = $item['teamA'];
                        $teamB = $item['teamB'];
                        $begin_time = date('Y-m-d H:i:s',$item['begin_time']);
                        $game_infos = $item['games'];
                        break;
                    }
                }

                $odds_info = array();
                foreach ($game_infos as $game_info) {
                    if ($gameid == $game_info['game_id']) {
                        $odds_info = $game_info;
                        break;
                    }
                }

                self::apiRequest("sendMessage",array("chat_id"=>$chat_id,"text"=>"Guess on $competition - $teamA vs $teamB at $begin_time , bet on $sp
To confirm your guess, send SOC to the address bellow. 
NOTE: MAKE SURE your 'sending address' is the same as your 'return address'. Because the rewards will be sent directly to the original address from which your SOC is transferred to the address bellow.  "));

                $keyBoardParam = array(
                    "chat_id"=>$chat_id,
                    "text"=>$odds_info['address_'.$sp],
                    "parse_mode"=>"Markdown",
                    'reply_markup' => array(
                        "keyboard" => array(array("complete")),
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true
                    )
                );
                self::apiRequest("sendMessage", $keyBoardParam);

            } else if ($text === "cancel") {
                $objRedis->delete($match_cache);
                $objRedis->delete($playing_cache);

                $keyBoardParam = array(
                    "chat_id"=>$chat_id,
                    "text"=>"your current progress has been cancelled.To make another guess,use /start command",
                    "parse_mode"=>"Markdown",
                    'reply_markup' => array(
                        'keyboard' => array(
                            array(
                                array(
                                    'text'=>"/start",
//                                'request_contact'=>false
                                )
                            )
                        ),

                        'one_time_keyboard' => true,
                        'resize_keyboard' => true
                    )
                );
                self::apiRequest("sendMessage", $keyBoardParam);
            }
        }
    }

    private static function errorProcess($chat_id) {
        $keyBoardParam = array(
            "chat_id"=>$chat_id,
            "text"=>"your current progress has been cancelled.To make another guess,use /start command",
            "parse_mode"=>"Markdown",
            'reply_markup' => array(
                'keyboard' => array(
                    array(
                        array(
                            'text'=>"/start",
//                                'request_contact'=>false
                        )
                    )
                ),

                'one_time_keyboard' => true,
                'resize_keyboard' => true
            )
        );
        self::apiRequest("sendMessage", $keyBoardParam);
    }

    private static function apiRequestWebhook($method, $parameters) {
        if (!is_string($method)) {
            error_log("Method name must be a string\n");
            return false;
        }

        if (!$parameters) {
            $parameters = array();
        } else if (!is_array($parameters)) {
            error_log("Parameters must be an array\n");
            return false;
        }

        $parameters["method"] = $method;

        header("Content-Type: application/json");
        echo json_encode($parameters);
        return true;
    }

    private static function exec_curl_request($handle) {
        $response = curl_exec($handle);

        if ($response === false) {
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            error_log("Curl returned error $errno: $error\n");
            curl_close($handle);
            return false;
        }

        $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
        curl_close($handle);

        if ($http_code >= 500) {
            // do not wat to DDOS server if something goes wrong
            sleep(10);
            return false;
        } else if ($http_code != 200) {
            $response = json_decode($response, true);
            error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
            if ($http_code == 401) {
                throw new Exception('Invalid access token provided');
            }
            return false;
        } else {
            $response = json_decode($response, true);
            if (isset($response['description'])) {
                error_log("Request was successful: {$response['description']}\n");
            }
            $response = $response['result'];
        }

        return $response;
    }

    public static function apiRequest($method, $parameters) {
        if (!is_string($method)) {
            error_log("Method name must be a string\n");
            return false;
        }

        if (!$parameters) {
            $parameters = array();
        } else if (!is_array($parameters)) {
            error_log("Parameters must be an array\n");
            return false;
        }

        foreach ($parameters as $key => &$val) {
            // encoding to JSON array parameters, for example reply_markup
            if (!is_numeric($val) && !is_string($val)) {
                $val = json_encode($val);
            }
        }
        $url = Conf::getConf("socguessbot.telegram_api_url").$method.'?'.http_build_query($parameters);

        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($handle, CURLOPT_TIMEOUT, 60);

        return self::exec_curl_request($handle);
    }


}
