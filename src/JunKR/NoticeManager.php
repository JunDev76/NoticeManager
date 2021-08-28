<?php

namespace JunKR;

use FormSystem\form\ButtonForm;
use FormSystem\form\CustomForm;
use FormSystem\form\ModalForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class NoticeManager extends PluginBase implements Listener{

    private $database, $db;

    public function onDisable(){
        $this->database->setAll($this->db);
        $this->database->save();
    }

    public function onEnable(){
        $this->database = new Config($this->getDataFolder() . 'data.yml', Config::YAML);
        $this->db = $this->database->getAll();

        $cmd = new PluginCommand("알림", $this);
        $cmd->setDescription('알림 관련 명령어 입니다');

        Server::getInstance()->getCommandMap()->register($this->getDescription()->getName(), $cmd);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if($command->getName() !== "알림"){
            return true;
        }

        $form = new ButtonForm(function(Player $player, $data){
            if($data === null){
                return;
            }
            if($data === 0){
                $this->keyword($player);
            }
        });

        $form->setTitle("§l알림 시스템");
        $form->setContent("\n");
        $form->addButton("§l키워드 알림");
        $form->addButton("창 닫기");

        $sender->sendForm($form);
        return true;
    }

    public function keyword(Player $player){
        $arr = $this->db[$player->getName()]["keyword"] ?? [];
        $form = new ButtonForm(function(Player $player, $data) use($arr) {
            if(!isset($arr[$data])){
                if((count($arr) + 1) === $data){
                    return;
                }
                $addf = new CustomForm(function(Player $player, $data){
                    if(!isset($data[0])){
                        $this->keyword($player);
                        return;
                    }
                    $keyword = trim($data[0]);
                    if(mb_strlen($keyword, "UTF-8") <= 0){
                        $this->keyword($player);
                        return;
                    }
                    $this->db[$player->getName()]["keyword"][] = $keyword;
                    $this->keyword($player);
                    return;
                });
                $addf->setTitle("§l키워드 추가");
                $addf->addInput("추가 할 키워드를 입력해주세요.", "", "스티브");
                $addf->sendForm($player);
                return;
            }

            $keyword = $arr[$data];

            $modal = new ModalForm(function(Player $player, $mdata) use($data) {
                if($mdata === true){
                    unset($this->db[$player->getName()]["keyword"][$data]);
                    $this->db[$player->getName()]["keyword"] = array_values($this->db[$player->getName()]["keyword"]);
                }

                $this->keyword($player);
            });
            $modal->setTitle("§l키워드 삭제");
            $modal->setContent("§e" . $keyword . "§f 을(를) 삭제 할까요?");
            $modal->setButton1("네, 삭제하세요!");
            $modal->setButton2("아니요, 삭제하지마세요!");
            $modal->sendForm($player);
        });

        $form->setTitle("§l키워드 알림 관리");
        $form->setContent("");
        foreach($arr as $item){
            $form->addButton("§l" . $item . "\n§r§8해당 키워드를 삭제합니다.");
        }
        $form->addButton("§l키워드 추가");
        $form->addButton("§l창 닫기");
        $form->sendForm($player);
    }

    /**
     * @priority HIGHEST
     */
    public function onChat(PlayerChatEvent $ev){
        $format = $ev->getFormat();
        $ev->setRecipients(array_filter($ev->getRecipients(), function($recipient) use ($format) : bool{
            if(!$recipient instanceof Player){
                return true;
            }
            $arr = $this->db[$recipient->getName()]["keyword"] ?? [];
            foreach($arr as $keyword){
                if(strpos(strtolower($format), $keyword) !== false){
                    $recipient->sendMessage("§f[키워드 알림]" . $format);
                    CrossUtils::playSound($recipient, "random.screenshot");
                    return false;
                }
            }
            return true;
        }));
    }

}