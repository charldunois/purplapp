<?php namespace Purplapp\Adn;

class PostClub
{
    use ArrayObjectTrait;

    public function html()
    {
        return "<a href=\"https://alpha.app.net/hashtags/{$this->url}\">{$this->name}</a>"
            . "<i>({$this->nicecount} posts)</i>";
    }
}
