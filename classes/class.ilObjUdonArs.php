<?php

declare(strict_types=1);

/**
 * Application class of the Udon ARS repository object.
 *
 * For now the object only consists of the standard ILIAS object data
 * (title, description). ARS specific data will follow.
 */
class ilObjUdonArs extends ilObjectPlugin
{
    protected function initType(): void
    {
        $this->setType(ilUdonArsPlugin::ID);
    }

    protected function doCreate(bool $clone_mode = false): void
    {
    }

    protected function doRead(): void
    {
    }

    protected function doUpdate(): void
    {
    }

    protected function doDelete(): void
    {
    }
}
