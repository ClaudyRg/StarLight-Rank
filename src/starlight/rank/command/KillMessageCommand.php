<?php

declare(strict_types=1);

namespace starlight\rank\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use starlight\rank\Main as StarRank;

class KillMessageCommand extends Command
{
    private StarRank $plugin;

    public function __construct(StarRank $plugin)
    {
        parent::__construct("killmsg", "Set your custom kill message", "/killmsg <message|off>", ["setkillmsg"]);
        $this->setPermission("starrank.perk.killmsg");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cThis command can only be used in-game.");
            return true;
        }

        if (!$this->testPermission($sender)) {
            return true;
        }

        if (empty($args)) {
            $sender->sendMessage("§eUsage: §f/killmsg <message|off>");
            $sender->sendMessage("§7Placeholders: §b{player} §7(Victim), §b{killer} §7(You), §b{rank} §7(Your Rank)");
            $current = $this->plugin->getCustomKillMessage($sender);
            if ($current !== null) {
                $sender->sendMessage("§7Current: §f" . $current);
            }
            return true;
        }

        if (strtolower($args[0]) === "off") {
            $this->plugin->setCustomKillMessage($sender, null);
            $sender->sendMessage("§aCustom kill message disabled.");
            return true;
        }

        $message = implode(" ", $args);
        if (strlen($message) > 100) {
            $sender->sendMessage("§cMessage is too long! Max 100 characters.");
            return true;
        }

        // Basic color code support
        $message = str_replace("&", "§", $message);

        $this->plugin->setCustomKillMessage($sender, $message);
        $sender->sendMessage("§aCustom kill message set to: §f" . $message);
        return true;
    }
}
