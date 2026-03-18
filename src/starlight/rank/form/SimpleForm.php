<?php

declare(strict_types=1);

namespace starlight\rank\form;

use pocketmine\form\Form;
use pocketmine\player\Player;

class SimpleForm implements Form
{
    private array $data = [];
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
        $this->data["type"] = "form";
        $this->data["title"] = "";
        $this->data["content"] = "";
        $this->data["buttons"] = [];
    }

    public function setTitle(string $title): void
    {
        $this->data["title"] = $title;
    }

    public function setContent(string $content): void
    {
        $this->data["content"] = $content;
    }

    public function addButton(string $text, int $imageType = -1, string $imagePath = ""): void
    {
        $content = ["text" => $text];
        if ($imageType !== -1) {
            $content["image"]["type"] = $imageType === 0 ? "path" : "url";
            $content["image"]["data"] = $imagePath;
        }
        $this->data["buttons"][] = $content;
    }

    public function handleResponse(Player $player, $data): void
    {
        if ($data === null) {
            return;
        }
        $callable = $this->callable;
        $callable($player, $data);
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
