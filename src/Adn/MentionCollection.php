<?php namespace Purplapp\Adn;

use stdClass;

class MentionCollection
{
    use DataCollectionTrait;

    protected function transform(stdClass $object)
    {
        return Mention::wrap($object);
    }
}