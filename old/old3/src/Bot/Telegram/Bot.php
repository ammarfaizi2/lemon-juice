<?php

namespace Bot\Telegram;

/**
 * @author Ammar Faizi <ammarfaizi2@gmail.com>
 * @license MIT
 */

defined("TOKEN") or require __DIR__."/../../../config/telegram.php";

use PDO;
use Sys\DB;
use Bot\Telegram\Command\Warn;
use Bot\Telegram\Traits\Command;
use Bot\Telegram\Traits\CommandHandler;

final class Bot
{
    use Command;
    use CommandHandler;

    /**
     * @var array
     */
    private $sticker = [];

    /**
     * @var string
     */
    private $text;

    /**
     * @var string
     */
    private $room_id;

    /**
     * @var string
     */
    private $user_id;

    /**
     * @var uname
     */
    private $uname;

    /**
     * @var string
     */
    private $actor;

    /**
     * @var string
     */
    private $actor_call;

    /**
     * @var string
     */
    private $chat_type;

    /**
     * @var string
     */
    private $msg_id;
    
    /**
     * @var array
     */
    private $input;

    /**
     * @var string
     */
    private $msg_type;

    /**
     * @var array
     */
    private $entities = [];

    /**
     * @var array
     */
    private $entities_pos = [];

    /**
     * @var string
     */
    private $special_comannd;

    /**
     * Run bot.
     * @param string $argv
     */
    public function run($arg)
    {
        $this->input = json_decode($arg, true);
        $this->parseEvent();
        switch ($this->msg_type) {
            case 'text':
                $this->parseEntities();
                $this->textFixer();
                if (!$this->command()) {
                }
                $this->chat_type != "private" and $this->notifer();
                break;
            case 'sticker':
                $this->chat_type != "private" and $this->notifer();
                break;
            default:
                // code...
                break;
        }
        $this->knower();
        $this->auto_ban();
        $this->auto_warn();
    }

    /**
     * Parse webhook event.
     */
    private function parseEvent()
    {
        if (isset($this->input['message']['text'])) {
            $this->msg_type = "text";
            $this->text = $this->input['message']['text'];
            $this->room_id = $this->input['message']['chat']['id'];
            $this->user_id = $this->input['message']['from']['id'];
            $this->uname = isset($this->input['message']['from']['username']) ? $this->input['message']['from']['username'] : null;
            $this->actor = $this->input['message']['from']['first_name']. (isset($this->input['message']['from']['last_name']) ? " ".$this->input['message']['from']['last_name'] : "");
            $this->actor_call = $this->input['message']['from']['first_name'];
            $this->chat_type = $this->input['message']['chat']['type'];
            $this->msg_id = $this->input['message']['message_id'];
            $this->entities_pos = isset($this->input['message']['entities']) ? $this->input['message']['entities'] : [];
        } elseif (isset($this->input['message']['sticker'])) {
            $this->msg_type = "sticker";
            $this->sticker = $this->input['message']['sticker'];
            $this->room_id = $this->input['message']['chat']['id'];
            $this->user_id = $this->input['message']['from']['id'];
            $this->uname = isset($this->input['message']['from']['username']) ? $this->input['message']['from']['username'] : null;
            $this->actor = $this->input['message']['from']['first_name']. (isset($this->input['message']['from']['last_name']) ? " ".$this->input['message']['from']['last_name'] : "");
            $this->actor_call = $this->input['message']['from']['first_name'];
            $this->chat_type = $this->input['message']['chat']['type'];
            $this->msg_id = $this->input['message']['message_id'];
        }
    }

    /**
     * Text fixer.
     */
    private function textFixer()
    {
        $sbt = substr($this->text, 0, 4);
        if ($sbt == "ask " || $sbt == "ask\n") {
            $this->text = "/".$this->text;
        } elseif (isset($this->input['message']['reply_to_message']['from']['username']) and $this->input['message']['reply_to_message']['from']['username'] == "MyIceTea_Bot") {
            switch ($this->input['message']['reply_to_message']['text']) {
                case 'Sebutkan ID Anime yang ingin kamu cari !':
                    $this->text = "/idan ".$this->text;
                    break;
                case 'Anime apa yang ingin kamu cari? ~':
                    $this->text = "/anime ".$this->text;
                    break;
                case 'Anime apa yang ingin kamu cari?':
                    $this->text = "/qanime ".$this->text;
                    break;

                case 'Sebutkan ID Manga yang ingin kamu cari !':
                    $this->text = "/idma ".$this->text;
                    break;
                case 'Manga apa yang ingin kamu cari? ~':
                    $this->text = "/manga ".$this->text;
                    break;
                case 'Manga apa yang ingin kamu cari?':
                    $this->text = "/qmanga ".$this->text;
                    break;

                case 'Balas pesan dengan screenshot anime yang ingin kamu tanyakan !':
                    $this->special_comannd = "/whatanime";
                    break;
                default:
                    $a = explode("\n", $this->input['message']['reply_to_message']['text'], 2);
                    var_dump($a);
                    switch ($a[0]) {
                        case 'Hasil pencarian anime :':
                            $this->text = "/idan ".$this->text;
                            break;
                        case 'Hasil pencarian manga :':
                            $this->text = "/idma ".$this->text;
                            break;
                        default:
                            break;
                    }
                    break;
            }
        }
    }

    private function knower()
    {
        if (isset($this->user_id)) {
            $is_private = $this->chat_type == "private" ? "true" : "false";
            $pdo = DB::pdoInstance();
            $st = $pdo->prepare("SELECT `userid`, `username`, `name`, `msg_count`, `is_private_known` FROM `a_known_users` WHERE `userid`=:userid LIMIT 1;");
            $st->execute(
                [
                    ":userid" => $this->user_id,
                ]
            );
            if ($st = $st->fetch(PDO::FETCH_ASSOC)) {
                if ($st['is_private_known'] == "true" and $is_private == "false") {
                    $is_private = "true";
                }
                $st['msg_count']++;
                $pdo->prepare("UPDATE `a_known_users` SET `username`=:username, `name`=:name, `msg_count`=:msg_count, `updated_at`=:up, `is_private_known`=:priv WHERE `userid`=:userid LIMIT 1;")->execute(
                    [
                        ":username" => strtolower($this->uname),
                        ":name" => $this->actor,
                        ":msg_count" => $st['msg_count'],
                        ":userid" => $this->user_id,
                        ":up" => date("Y-m-d H:i:s"),
                        ":priv" => $is_private
                    ]
                );
            } else {
                $pdo->prepare("INSERT INTO `a_known_users` (`userid`, `username`, `name`, `created_at`, `updated_at`, `msg_count`, `is_private_known`) VALUES (:userid, :username, :name, :created_at, :updated_at, :msg_count, :priv_known)")->execute(
                    [
                        ":userid" => $this->user_id,
                        ":username" => strtolower($this->uname),
                        ":name" => $this->actor,
                        "created_at" => date("Y-m-d H:i:s"),
                        ":updated_at"=>null,
                        ":msg_count"=> 1,
                        ":priv_known" => $is_private
                    ]
                );
            }
        }
    }

    private function parseEntities()
    {
        foreach ($this->entities_pos as $val) {
            if ($val['type'] == "mention") {
                $this->entities[$val['type']][] = substr($this->text, $val['offset']+1, $val['length']);
            } elseif ($val['type'] == "text_mention") {
                $this->entities[$val['type']][] = $val['user']['id'];
            } elseif ($val['type'] == "url") {
                $this->entities[$val['type']][] = substr($this->text, $val['offset']+1, $val['length']);
            }
        }
    }

    private function notifer()
    {
        $flagger = false;
        if (isset($this->entities['mention'])) {
            foreach ($this->entities['mention'] as $val) {
                if ($st = $this->check_recognized($val)) {
                    if ($st['is_private_known'] == "true") {
                        $context = isset($this->text) ? "<pre>".htmlspecialchars($this->text)."</pre>" : (isset($this->sticker) ? json_encode($this->sticker, 128) : "Error, please report to @LTMGroup");
                        if ($st['is_notifed'] == "false") {
                            B::sendMessage("It often happens, in groups, to tag (mention) an user or to reply (quote) to one of his messages, and that he miss the related notification among all the others. In addition, if you have more than one notification all coming from the the same group, once opened the chat they're all lost forever!\n\nSo, I'll notify you when someone tags you, i.e. mentions you, using your username.", $st['userid'], null, ['parse_mode'=>"HTML"]);
                            $this->recognized($st['userid']);
                        }
                        if (isset($this->uname)) {
                            $mentioner = "@".$this->uname;
                        } else {
                            $mentioner = $this->actor_call;
                        }

                        if (isset($this->input['message']['chat']['username'])) {
                            $room = "<a href=\"https://telegram.me/".$this->input['message']['chat']['username']."\">".$this->input['message']['chat']['title']."</a>";
                            $op = ['parse_mode'=>'HTML', 'disable_web_page_preview'=>true, "reply_markup"=>json_encode(["inline_keyboard"=>[[["text"=>"Go to the message","url"=> "https://telegram.me/".$this->input['message']['chat']['username']."/".$this->msg_id]]]])];
                        } else {
                            $room = "<b>".$this->input['message']['chat']['title']."</b>";
                            $op = ['parse_mode'=>'HTML', 'disable_web_page_preview'=>true];
                        }
                        B::sendMessage("{$mentioner} mentioned you in {$room}\n\n<pre>".htmlspecialchars($this->text)."</pre>", $st['userid'], null, $op);
                        $flagger = true;
                    }
                }
            }
        }
        if (isset($this->entities['text_mention'])) {
            foreach ($this->entities['text_mention'] as $val) {
                if ($st = $this->check_recognized($val, "userid")) {
                    if ($st['is_private_known'] == "true") {
                        $context = isset($this->text) ? "<pre>".htmlspecialchars($this->text)."</pre>" : (isset($this->sticker) ? json_encode($this->sticker, 128) : "Error, please report to @LTMGroup");
                        if ($st['is_notifed'] == "false") {
                            B::sendMessage("It often happens, in groups, to tag (mention) an user or to reply (quote) to one of his messages, and that he miss the related notification among all the others. In addition, if you have more than one notification all coming from the the same group, once opened the chat they're all lost forever!\n\nSo, I'll notify you when someone tags you, i.e. mentions you, using your username.", $st['userid'], null, ['parse_mode'=>"HTML"]);
                            $this->recognized($st['userid']);
                        }
                        if (isset($this->uname)) {
                            $mentioner = "@".$this->uname;
                        } else {
                            $mentioner = $this->actor_call;
                        }
                        if (isset($this->input['message']['chat']['username'])) {
                            $room = "<a href=\"https://telegram.me/".$this->input['message']['chat']['username']."\">".$this->input['message']['chat']['title']."</a>";
                            $op = ['parse_mode'=>'HTML', 'disable_web_page_preview'=>true, "reply_markup"=>json_encode(["inline_keyboard"=>[[["text"=>"Go to the message","url"=> "https://telegram.me/".$this->input['message']['chat']['username']."/".$this->msg_id]]]])];
                        } else {
                            $room = "<b>".$this->input['message']['chat']['title']."</b>";
                            $op = ['parse_mode'=>'HTML', 'disable_web_page_preview'=>true];
                        }
                        B::sendMessage("{$mentioner} mentioned you in {$room}\n\n{$context}", $st['userid'], null, $op);
                        $flagger = true;
                    }
                }
            }
        }
        if ($flagger === false and isset($this->input['message']['reply_to_message']['from']['id'])) {
            if ($st = $this->check_recognized($this->input['message']['reply_to_message']['from']['id'], "userid")) {
                if ($st['is_private_known'] == "true") {
                    $context = isset($this->text) ? "<pre>".htmlspecialchars($this->text)."</pre>" : (isset($this->sticker) ? json_encode($this->sticker, 128) : "Error, please report to @LTMGroup");
                    if ($st['is_notifed'] == "false") {
                        B::sendMessage("It often happens, in groups, to tag (mention) an user or to reply (quote) to one of his messages, and that he miss the related notification among all the others. In addition, if you have more than one notification all coming from the the same group, once opened the chat they're all lost forever!\n\nSo, I'll notify you when someone tags you, i.e. mentions you, using your username.", $st['userid'], null, ['parse_mode'=>"HTML"]);
                        $this->recognized($st['userid']);
                    }
                    if (isset($this->uname)) {
                        $mentioner = "@".$this->uname;
                    } else {
                        $mentioner = $this->actor_call;
                    }
                    if (isset($this->input['message']['chat']['username'])) {
                        $room = "<a href=\"https://telegram.me/".$this->input['message']['chat']['username']."\">".$this->input['message']['chat']['title']."</a>";
                        $op = ['parse_mode'=>'HTML', 'disable_web_page_preview'=>true, "reply_markup"=>json_encode(["inline_keyboard"=>[[["text"=>"Go to the message","url"=> "https://telegram.me/".$this->input['message']['chat']['username']."/".$this->msg_id]]]])];
                    } else {
                        $room = "<b>".$this->input['message']['chat']['title']."</b>";
                        $op = ['parse_mode'=>'HTML', 'disable_web_page_preview'=>true];
                    }
                    B::sendMessage("{$mentioner} replied to your message in {$room}\n\n{$context}", $st['userid'], null, $op);
                }
            }
        }
    }

    /**
     * Check recognized
     */
    private function check_recognized($username, $fl = "username")
    {
        $st = DB::pdoInstance()->prepare("SELECT `userid`,`is_private_known`,`is_notifed` FROM `a_known_users` WHERE `{$fl}`=:username LIMIT 1;");
        $st->execute(
            [
                ":username" => strtolower($username)
            ]
        );
        $st = $st->fetch(PDO::FETCH_ASSOC);
        return $st;
    }

    private function recognized($userid)
    {
        return DB::pdoInstance()->prepare("UPDATE `a_known_users` SET `is_notifed`='true' WHERE `userid`=:userid LIMIT 1;")->execute([':userid'=>$userid]);
    }

    private function auto_ban()
    {
        if (isset($this->entities['url']) && $this->chat_type != "private") {
            $list_pattern = [
                "cashbot"       => "Cash bot scam.",
                "botcash"       => "Cash bot scam.",
                "cashrobot"     => "Cash bot scam.",
                "cashzoo"       => "Cash bot scam.",
                "bitcoin"       => "Bitcoin scam.",
                "maifam"        => "Fuck game scam.",
                "dinopark"      => "Fuck game scam.",
                "mfarm"         => "Fuck game scam.",
                "happyfarm"     => "Fuck game scam.",
                "pirate_bay"    => "Fuck game scam.",
                "miningbtc"     => "Mining cryptocurrency.",
                "minergate"     => "Mining cryptocurrency.",
                "ltcminer"      => "Mining cryptocurrency.",
                "blockchain"    => "Mining cryptocurrency.",
                "hexamining"    => "Mining cryptocurrency.",
                "microhash"     => "Mining cryptocurrency.",
                "btcprominer"   => "Mining cryptocurrency.",
                "topbtcsites"   => "Mining cryptocurrency.",
                "knolix"        => "Mining cryptocurrency.",
                "cryptorush"    => "Mining cryptocurrency.",
                "rentmania"     => "Mining cryptocurrency.",
                "hash-mlm"      => "Scam.",
                "adbanner"      => "Advertising.",
                "hentai"        => "Hentai link.",
                "porn"          => "Pornograpy.",
                "sex"           => "Pornograpy."
            ];
            foreach ($this->entities['url'] as $url) {
                foreach ($list_pattern as $key => $val) {
                    if (strpos(strtolower($url), $key) !== false) {
                        $a = B::restrictChatMember(
                            [
                            "chat_id" => $this->room_id,
                            "user_id" => $this->user_id
                            ]
                        );
                        $b = B::kickChatMember($this->room_id, $this->user_id);
                        $user = "<b>Auto banned :</b>\n<a href=\"https://telegram.me/".$this->uname."\">".$this->actor_call."</a> has been banned!\n\n<b>Reason :</b>\n{$val}";
                        if ($a == '{"ok":true,"result":true}' or $b == '{"ok":true,"result":true}') {
                            B::sendMessage($user, $this->room_id, $this->msg_id, ["parse_mode"=>"HTML", 'disable_web_page_preview'=>true]);
                        } else {
                            B::sendMessage($user."\n\n".$a."\n".$b, $this->room_id, $this->msg_id, ["parse_mode"=>"HTML", 'disable_web_page_preview'=>true]);
                        }
                        $stop = true;
                        break;
                    }
                }
                if (isset($stop)) {
                    break;
                }
            }
        }
    }

    private function auto_warn()
    {
        if ($this->chat_type != "private" && isset($this->text)) {
            $a = ["anjeng", "anjing", "anjir", "anus", "asu", "bajigur", "bajingan", "banci", "bangsat", "bawok", "bego", "bejad", "bencong", "berengsek", "bf", "bluefilm", "bokep", "bokong", "bolot", "brengsek", "budek", "cangkemmu", "cekik", "celeh", "cewek murahan", "cewek telanjang", "cewek tidur", "cocot", "cocote", "cuk", "dada", "dada besar", "damput", "dancok", "dancuk", "dapurmu", "dengkulmu", "eek", "gak waras", "gamblus", "gancok", "gancuk", "gathel", "geblek", "gembel", "gendeng", "goblok", "hohohihe", "huasyu", "idiot", "idoni", "itel", "itil", "jablay", "jaran", "jancik", "jancok", "jancuk", "jangkrik", "jeh", "jelek", "jembot", "jembut", "joh", "juh", "kampret", "kampungan", "kemaluan", "katrok", "kenthu", "kentu", "keparat", "kepet", "kimpet", "kirek", "kirik", "kere", "kojor", "kontol", "kunyuk", "lakang", "lambe", "lambemu", "lesbi", "lola", "lonte", "lonthe", "lubang kemaluan", "maho", "mampus", "mani", "maria ozawa", "matamu", "matamu cok", "matane", "membuta", "memek", "miyabi", "modar", "ndasmu", "ngaceng", "ngapleki", "ngehe", "ngentot", "ngewe", "ngocok", "norak", "ola olo", "oral", "orang gila", "orang stress", "orgasm", "orgasme", "pantat", "payah", "payudara", "pejoh", "pejuh", "pekok", "peler", "peli", "penis", "pentel", "pentil", "pepek", "perek", "perkosa", "persetan", "picek", "purel", "puting", "raimu", "sange", "sarap", "seks", "semprul", "sial", "sinting", "sontoloyo", "sperma", "tai", "taik", "taek", "telanjang", "temempik", "tempek", "tempik", "tetek", "tolol", "tua bangka", "turok", "uasu", "udik", "wanita jalang", "wanita murahan", "wanita telanjang", "wanita tidur", "vagina"];
            $tgg = explode(" ", $tgq = strtolower($this->text));
            foreach ($a as $b) {
                $c = explode(" ", $b) xor $cond = null;
                foreach ($c as $d) {
                    $cond = isset($cond) ? $cond && in_array($d, $tgg) : in_array($d, $tgg);
                }
                if ($cond === true) {
                    break;
                }
            }
            if ($cond) {
                $st = new Warn(
                    [
                        "uifd" => $this->user_id."|".$this->room_id,
                        "userid" => $this->user_id,
                        "reason" => "Bad word.",
                        "room_id" => $this->room_id,
                        "warner" => 'auto',
                        "msg_id" => $this->msg_id,
                        "username" => $this->uname,
                        "actor" => $this->actor_call,
                        "reply_to" => $this->msg_id,
                        "auto" => true
                    ]
                );
                $st->run();
            }
        }
    }
}
