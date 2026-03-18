<?php

declare(strict_types=1);

namespace starlight\rank\form;

use pocketmine\form\Form;
use pocketmine\player\Player;

class ModalForm implements Form
{
    private array $data = [];
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
        $this->data["type"] = "modal";
        $this->data["title"] = "";
        $this->data["content"] = "";
        $this->data["button1"] = "";
        $this->data["button2"] = "";
    }

    public function setTitle(string $title): void
    {
        $this->data["title"] = $title;
    }

    public function setContent(string $content): void
    {
        $this->data["content"] = $content;
    }

    public function setButton1(string $text): void
    {
        $this->data["button1"] = $text;
    }

    public function setButton2(string $text): void
    {
        $this->data["button2"] = $text;
    }

    public function handleResponse(Player $player, $data): void
    {
        $callable = $this->callable;
        $callable($player, $data);
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
