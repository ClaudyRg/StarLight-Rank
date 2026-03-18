<?php

declare(strict_types = 1)
;

namespace starlight\rank;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use starlight\rank\command\RankShopCommand;

class Main extends PluginBase
{
    use SingletonTrait;

    private string $dbPath = "C:\\Users\\GamingPC\\Documents\\StarLight\\Web Server\\web-server\\starlight.db";
    private array $ranksCache = [];
    private array $killMessagesCache = [];

    protected function onLoad(): void
    {
        self::setInstance($this);
    }

    protected function onEnable(): void
    {
        $this->saveDefaultConfig();

        if (!file_exists($this->dbPath)) {
            $this->getLogger()->warning("Database file not found at: " . $this->dbPath);
            $this->getLogger()->warning("StarRank might not sync correctly with the web dashboard.");
        }

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

        $this->getServer()->getCommandMap()->register("starrank", new RankShopCommand());
        $this->getServer()->getCommandMap()->register("starrank", new \starlight\rank\command\KillMessageCommand($this));
    }

    protected function onDisable(): void
    {
    // SQLite doesn't need explicit save here
    }

    private function getDb(): \SQLite3
    {
        $db = new \SQLite3($this->dbPath);
        $db->exec("PRAGMA journal_mode = WAL;");
        $db->exec("PRAGMA busy_timeout = 5000;");
        return $db;
    }

    public function getRank(string|Player $player): string
    {
        $name = $player instanceof Player ? $player->getName() : $player;
        $name = strtolower($name);

        if (isset($this->ranksCache[$name])) {
            return $this->ranksCache[$name];
        }

        $defaultRank = $this->getConfig()->get("default-rank", "Member");

        try {
            $db = $this->getDb();
            $stmt = $db->prepare("SELECT rank FROM users WHERE LOWER(gamertag) = LOWER(:name)");
            $stmt->bindValue(":name", $name, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $db->close();

            $rank = $row && !empty($row['rank']) ? $row['rank'] : $defaultRank;
        }
        catch (\Exception $e) {
            $this->getLogger()->error("Failed to fetch rank: " . $e->getMessage());
            $rank = $defaultRank;
        }

        $this->ranksCache[$name] = $rank;
        return $rank;
    }

    public function setRank(string|Player $player, string $rank): bool
    {
        $name = $player instanceof Player ? $player->getName() : $player;
        $name = strtolower($name);

        $this->getLogger()->info("§eUpdating rank for {$name} to {$rank}...");

        $availableRanks = array_keys($this->getConfig()->get("ranks", []));

        $matchedRank = null;
        foreach ($availableRanks as $availableRank) {
            if (strtolower($availableRank) === strtolower($rank)) {
                $matchedRank = $availableRank;
                break;
            }
        }

        if ($matchedRank === null) {
            $this->getLogger()->error("§cRank '{$rank}' not found in config!");
            return false;
        }

        try {
            $db = $this->getDb();

            // First ensure user exists
            $insert = $db->prepare("INSERT OR IGNORE INTO users (gamertag) VALUES (:name)");
            $insert->bindValue(":name", $name, SQLITE3_TEXT);
            $insert->execute();

            // Then update rank
            $stmt = $db->prepare("UPDATE users SET rank = :rank WHERE LOWER(gamertag) = LOWER(:name)");
            $stmt->bindValue(":rank", $matchedRank, SQLITE3_TEXT);
            $stmt->bindValue(":name", $name, SQLITE3_TEXT);
            $stmt->execute();
            $db->close();

            $this->ranksCache[$name] = $matchedRank;

            $this->getLogger()->info("§aSuccessfully updated {$name}'s rank to {$matchedRank} in database.");

            // Force nametag update if player is online
            $onlinePlayer = $this->getServer()->getPlayerExact($name);
            if ($onlinePlayer !== null) {
                $this->updateNametag($onlinePlayer);
            }
            return true;
        }
        catch (\Exception $e) {
            $this->getLogger()->error("Failed to set rank: " . $e->getMessage());
            return false;
        }
    }

    public function getRankColor(string $rank): string
    {
        return $this->getConfig()->getNested("ranks.{$rank}.color", "§7");
    }

    public function getFormattedRank(Player $player): string
    {
        $rank = $this->getRank($player);
        $color = $this->getRankColor($rank);
        return $color . $rank;
    }

    public function getCustomKillMessage(string|Player $player): ?string
    {
        $name = $player instanceof Player ? $player->getName() : $player;
        $name = strtolower($name);

        if (isset($this->killMessagesCache[$name])) {
            return $this->killMessagesCache[$name];
        }

        try {
            $db = $this->getDb();
            $stmt = $db->prepare("SELECT custom_kill_message FROM users WHERE LOWER(gamertag) = LOWER(:name)");
            $stmt->bindValue(":name", $name, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $db->close();

            $message = $row && !empty($row['custom_kill_message']) ? $row['custom_kill_message'] : null;
        }
        catch (\Exception $e) {
            $this->getLogger()->error("Failed to fetch custom kill message: " . $e->getMessage());
            $message = null;
        }

        $this->killMessagesCache[$name] = $message;
        return $message;
    }

    public function setCustomKillMessage(string|Player $player, ?string $message): bool
    {
        $name = $player instanceof Player ? $player->getName() : $player;
        $name = strtolower($name);

        try {
            $db = $this->getDb();
            // Ensure user exists
            $insert = $db->prepare("INSERT OR IGNORE INTO users (gamertag) VALUES (:name)");
            $insert->bindValue(":name", $name, SQLITE3_TEXT);
            $insert->execute();

            $stmt = $db->prepare("UPDATE users SET custom_kill_message = :msg WHERE LOWER(gamertag) = LOWER(:name)");
            $stmt->bindValue(":msg", $message, SQLITE3_TEXT);
            $stmt->bindValue(":name", $name, SQLITE3_TEXT);
            $stmt->execute();
            $db->close();

            $this->killMessagesCache[$name] = $message;
            return true;
        }
        catch (\Exception $e) {
            $this->getLogger()->error("Failed to set custom kill message: " . $e->getMessage());
            return false;
        }
    }

    public function getRankUnicode(string $rank): string
    {
        return $this->getConfig()->getNested("ranks.{$rank}.unicode", "");
    }

    public function formatString(string $format, Player $player, string $message = ""): string
    {
        $rank = $this->getRank($player);
        $color = $this->getRankColor($rank);
        
        $worldName = strtolower($player->getWorld()->getFolderName());
        $isLobby = (str_contains($worldName, "lobby") || str_contains($worldName, "hub") || str_contains($worldName, "waiting"))
            && !str_contains($worldName, "Solo-Lobby-lobby")
            && !str_contains($worldName, "Doubles-Lobby-lobby");

        // Hide "Member" rank text
        $rankDisplay = (strtolower($rank) === "member") ? "" : $color . $rank . " ";
        
        $levelText = "";
        $levelColor = "§7";
        $core = $this->getServer()->getPluginManager()->getPlugin("StarCore");
        if($core instanceof \starlight\core\Main){
            $level = $core->getLevel($player);
            $levelColor = $core->getLevelColor($level);
            if ($isLobby) {
                $levelText = $levelColor . $level;
            }
        }

        $search = ["{rank}", "{player}", "{msg}", "{color}", "{unicode}", "{level}"];
        $replace = [$rankDisplay, $player->getName(), $message, $color, "", $levelText];

        return trim(str_replace($search, $replace, $format));
    }

    public function updateNametag(Player $player, bool $includeNametag = true): void
    {
        $rank = $this->getRank($player);
        $format = $this->getConfig()->getNested("ranks.{$rank}.nametag-format", $this->getConfig()->get("nametag-format", "{level} {rank}{player}"));
        $formattedTag = $this->formatString($format, $player);

        // Remove empty unicode brackets and extra spaces
        $formattedTag = str_replace(["[]", "  "], ["", " "], $formattedTag);

        if ($includeNametag) {
            $player->setNameTag($formattedTag);
        }
        $player->setDisplayName($formattedTag);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() !== "setrank") {
            return false;
        }

        if (!$sender->hasPermission("starrank.command.admin")) {
            $sender->sendMessage("§cYou don't have permission to use this command.");
            return true;
        }

        if (count($args) < 2) {
            $sender->sendMessage("§cUsage: /setrank <player> <rank>");
            $available = implode(", ", array_keys($this->getConfig()->get("ranks", [])));
            $sender->sendMessage("§7Available ranks: §f" . $available);
            return true;
        }

        $targetName = $args[0];
        $rankName = $args[1];

        if ($this->setRank($targetName, $rankName)) {
            $sender->sendMessage("§aSuccessfully updated §f{$targetName}§a's rank to §e{$rankName}§a.");
        }
        else {
            $sender->sendMessage("§cInvalid rank. Available ranks: " . implode(", ", array_keys($this->getConfig()->get("ranks", []))));
        }

        return true;
    }
}
