<?php

declare(strict_types=1);

namespace starlight\rank;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\chat\LegacyRawChatFormatter;
use pocketmine\player\Player;

class EventListener implements Listener
{
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $this->plugin->updateNametag($player);

        // Disabled global join message to prevent double chat
        $event->setJoinMessage("");
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        // Disabled global quit message
        $event->setQuitMessage("");
    }

    public function onChat(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $message = $event->getMessage();

        $rank = $this->plugin->getRank($player);
        $format = $this->plugin->getConfig()->getNested("ranks.{$rank}.chat-format", $this->plugin->getConfig()->get("chat-format", "{unicode}[{rank}] {player} >>> {msg}"));
        $formattedChat = $this->plugin->formatString($format, $player, $message);

        $event->setFormatter(new LegacyRawChatFormatter($formattedChat));

        $world = $player->getWorld();
        $worldName = strtolower($world->getFolderName());
        $isLobby = (str_contains($worldName, "lobby") || str_contains($worldName, "hub") || str_contains($worldName, "waiting"))
            && !str_contains($worldName, "Solo-Lobby-lobby")
            && !str_contains($worldName, "Doubles-Lobby-lobby");

        if ($isLobby) {
            $recipients = $event->getRecipients();
            $recipients = array_filter($recipients, function($recipient) {
                if (!$recipient instanceof Player) return false;
                $rWorldName = strtolower($recipient->getWorld()->getFolderName());
                return (str_contains($rWorldName, "lobby") || str_contains($rWorldName, "hub") || str_contains($rWorldName, "waiting"))
                    && !str_contains($rWorldName, "Solo-Lobby-lobby")
                    && !str_contains($rWorldName, "Doubles-Lobby-lobby");
            });
            $event->setRecipients($recipients);
        }
    }
}
