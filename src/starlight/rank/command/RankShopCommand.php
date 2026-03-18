<?php

declare(strict_types=1);

namespace starlight\rank\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use StarGems\Main as StarGems;
use starlight\rank\Main as StarRank;
use starlight\rank\form\SimpleForm;
use starlight\rank\form\ModalForm;

class RankShopCommand extends Command
{
    private array $rankPrices = [
        "VIP" => 500,
        "VIP+" => 1000,
        "MVP" => 2000,
        "MVP+" => 3500,
        "Legend" => 6000
    ];

    public function __construct()
    {
        parent::__construct("rankshop", "Buy ranks using StarGems", "/rankshop", ["buyrank"]);
        $this->setPermission("starrank.command.rankshop");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cThis command can only be used in-game.");
            return true;
        }

        $this->openRankShop($sender);
        return true;
    }

    private function openRankShop(Player $player): void
    {
        $gemsPlugin = $player->getServer()->getPluginManager()->getPlugin("StarGems");
        if (!$gemsPlugin instanceof StarGems) {
            $player->sendMessage("§cStarGems plugin is not available. Rank shop is closed.");
            return;
        }

        $currentGems = $gemsPlugin->getGems($player);

        $form = new SimpleForm(function (Player $submitter, ?int $data) use ($currentGems): void {
            if ($data === null) {
                return;
            }

            $ranks = array_keys($this->rankPrices);
            if (!isset($ranks[$data])) {
                return;
            }

            $selectedRank = $ranks[$data];
            $price = $this->rankPrices[$selectedRank];

            $this->openConfirmationForm($submitter, $selectedRank, $price, $currentGems);
        });

        $form->setTitle("§l§bStar§fLight §eRank Shop");
        $form->setContent("§7You have: §e{$currentGems} StarGems\n\n§fSelect a rank to purchase:");

        foreach ($this->rankPrices as $rank => $price) {
            $color = StarRank::getInstance()->getRankColor($rank);
            $form->addButton("{$color}§l{$rank}\n§r§8Price: §e{$price} Gems");
        }

        $player->sendForm($form);
    }

    private function openConfirmationForm(Player $player, string $rank, int $price, int $currentGems): void
    {
        $form = new ModalForm(function (Player $submitter, ?bool $data) use ($rank, $price): void {
            if ($data === null || $data === false) {
                $submitter->sendMessage("§cPurchase cancelled.");
                return;
            }

            $gemsPlugin = $submitter->getServer()->getPluginManager()->getPlugin("StarGems");
            if (!$gemsPlugin instanceof StarGems) {
                $submitter->sendMessage("§cAn error occurred. Please try again later.");
                return;
            }

            // Verify they still have enough gems
            $currentGems = $gemsPlugin->getGems($submitter);
            if ($currentGems < $price) {
                $submitter->sendMessage("§cYou don't have enough StarGems to buy §f{$rank}§c. You need §e" . ($price - $currentGems) . " §cmore.");
                return;
            }

            // Deduct gems and apply rank
            if ($gemsPlugin->reduceGems($submitter, $price)) {
                StarRank::getInstance()->setRank($submitter, $rank);
                $submitter->sendMessage("§a§l★ §r§aCongratulations! You have purchased the §e{$rank} §arank for §e{$price} StarGems§a!");

                // Announce to server
                $submitter->getServer()->broadcastMessage("§b§lStarLight §r§8» §f{$submitter->getName()} §ajust bought the §e{$rank} §arank from the Rank Shop!");
            } else {
                $submitter->sendMessage("§cTransaction failed.");
            }
        });

        $color = StarRank::getInstance()->getRankColor($rank);
        $form->setTitle("§l§8Confirm Purchase");

        $content = "§7Are you sure you want to buy the {$color}§l{$rank}§r §7rank?\n\n";
        $content .= "§8• §fPrice: §e{$price} StarGems\n";
        $content .= "§8• §fYour Balance: §e{$currentGems} StarGems\n";

        if ($currentGems < $price) {
            $content .= "\n§c⚠ You do not have enough StarGems!";
        } else {
            $content .= "\n§aRemaining Balance: §e" . ($currentGems - $price) . " StarGems";
        }

        $form->setContent($content);
        $form->setButton1("§a§lConfirm");
        $form->setButton2("§c§lCancel");

        $player->sendForm($form);
    }
}
