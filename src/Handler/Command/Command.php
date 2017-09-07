<?php

namespace Handler\Command;

use DB;
use PDO;
use Telegram as B;
use Handler\Command\MyAnimeListCMD;

trait Command
{
    private function __command()
    {
        $__command_list = [
            "/anime"    => ["!anime", "~anime"],
            "/idan"     => ["!idan", "~idan"],
            "/manga"    => ["!manga", "~manga"],
            "/idma"     => ["!idma", "~idma"],
            "/start"    => ["!start", "~start"],
            "/time"     => ["!time", "~time"],
            "/ping"     => ["!ping", "~ping"],
            "/report"   => ["!report", "~report"],
            "/kick"     => ["!kick", "~kick"],
            "/ban"      => ["!ban", "~ban"],
            "/unban"    => ["!unban", "~unban"],
            "/nowarn"   => ["!nowarn", "~nowarn"],
            "/warn"     => ["!warn", "~nowarn"],
            "/help"     => ["!help", "~help"],
            "/welcome"  => ["!welcome", "~welcome"]
        ];
        $cmd = explode(" ", $this->text, 2);
        $param = isset($cmd[1]) ? trim($cmd[1]) : "";
        $cmd = explode("@", $cmd[0], 2);
        $cmd = strtolower($cmd[0]);
        $flag = false;
        foreach ($__command_list as $key => $val) {
            if ($cmd == $key) {
                $r = $this->__do_command($key, $param);
                break;
            } else {
                foreach ($val as $vel) {
                    if ($cmd == $vel) {
                        $r = $this->__do_command($key, $param);
                        $flag = true;
                        break;
                    }
                }
                if ($flag) {
                    break;
                }
            }
        }
        return $r;
    }

    private function __do_command($command, $param = null)
    {
        switch ($command) {
            case '/anime':
                $app = new MyAnimeListCMD($this);
                return $app->__anime($param);
                break;
            case '/idan':
                $app = new MyAnimeListCMD($this);
                return $app->__idan($param);
                break;
            case '/manga':
                $app = new MyAnimeListCMD($this);
                return $app->__manga($param);
                break;
            case '/idma':
                $app = new MyAnimeListCMD($this);
                return $app->__idma($param);
                break;
            case '/start':
                return B::sendMessage(
                    [
                        "text" => "Hai ".$this->actorcall.", ketik /help untuk menampilkan menu!",
                        "chat_id" => $this->chatid,
                        "reply_to_message_id" => $this->msgid,
                    ]
                );
                break;
            case '/help':
                return B::sendMessage([
                    "text" =>   "<b>Time :</b>".
                                "\n/time : Menampilkan waktu saat ini (Asia/Jakarta).".
                                "\n\n<b>Anime :</b>".
                                "\n/anime [spasi] [nama anime] : Mencari anime.".
                                "\n/idan [spasi] [id_anime] : Info anime.".
                                "\n\n<b>Manga :</b>".
                                "\n/manga [spasi] [nama_manga] : Mencari manga.".
                                "\n/idma [spasi] [id_manga] : Info manga."
                    ,
                    "chat_id" => $this->chatid,
                    "reply_to_message_id" => $this->msgid,
                    "parse_mode" => "HTML"
                ]);
                break;
            case '/time':
                return B::sendMessage([
                    "text" => date("Y-m-d H:i:s", (time() + (3600 * 7))),
                    "chat_id" => $this->chatid,
                    "reply_to_message_id" => $this->msgid
                ]);
                break;
            case '/ping':
                return B::sendMessage([
                    "text" => (time() - $this->event['message']['date'])." s",
                    "chat_id" => $this->chatid,
                    "reply_to_message_id" => $this->msgid
                ]);
            break;
            case '/ban':
                $flag = false;
                $a = json_decode(B::getChatAdministrators([
                        "chat_id" => $this->chatid
                    ], "GET")['content'], true);
                foreach ($a['result'] as $val) {
                    if ($val['user']['id'] == $this->userid) {
                        if ($val['can_restrict_members'] || $val['status']=="creator") {
                            $flag = true;
                        }
                        break;
                    }
                }
                if ($flag){
                    
                    $a = B::kickChatMember(
                        [
                            "chat_id" => $this->chatid,
                            "user_id" => $this->replyto['from']['id']
                        ]
                    );
                    $b = B::restrictChatMember(
                        [
                            "chat_id" => $this->chatid,
                            "user_id" => $this->replyto['from']['id']
                        ]
                    );
                    if ($a['content'] == '{"ok":true,"result":true}' or $b['content'] == '{"ok":true,"result":true}') {
                        return B::sendMessage([
                                "text" => '<a href="tg://user?id='.$this->userid.'">'.$this->actorcall.'</a> banned <a href="tg://user?id='.$this->replyto['from']['id'].'">'.$this->replyto['from']['first_name']."</a>!",
                                "chat_id" => $this->chatid,
                                "parse_mode" => "HTML"
                            ]);
                    } else {
                        return B::sendMessage([
                            "chat_id" => $this->chatid,
                            "text" => "<b>Error</b> : \n<pre>".htmlspecialchars(json_decode($a['content'], true)['description'])."</pre>",
                            "parse_mode" => "HTML",
                            "reply_to_message_id" => $this->msgid
                        ]);    
                    }
                } else {
                    return B::sendMessage([
                            "chat_id" => $this->chatid,
                            "text" => "You are not allowed to use this command !",
                            "reply_to_message_id" => $this->msgid
                        ]);
                }
                return false;
                break;
            case '/welcome':
                if ($this->__set_welcome($param)) {
                    return B::sendMessage([
                        "text" => "Berhasil setting welcome message!",
                        "chat_id" => $this->chatid,
                        "reply_to_message_id" => $this->msgid
                    ]);
                }
                break;
        }
    }

    private function __set_welcome($msg)
    {
        $st = DB::prepare("UPDATE `a_known_groups` SET `welcome_message`=:wm WHERE `group_id`=:gi LIMIT 1;");
        $exe = $st->execute([
                ":gi" => $this->chatid,
                ":wm" => $msg
            ]);
        if (!$exe) {
            var_dump($st->errorInfo());
            print "\n\n";
        }
        return $exe;
    }
}
