<?php

declare(strict_types=1);

/**
 * List GUI of the Udon ARS repository object (presentation in the
 * repository container lists).
 */
class ilObjUdonArsListGUI extends ilObjectPluginListGUI
{
    public function initType(): void
    {
        $this->setType(ilUdonArsPlugin::ID);
    }

    public function getGuiClass(): string
    {
        return ilObjUdonArsGUI::class;
    }

    public function initCommands(): array
    {
        return [
            [
                'permission' => 'read',
                'cmd' => 'showContent',
                'default' => true,
            ],
            [
                'permission' => 'write',
                'cmd' => 'editProperties',
                'txt' => $this->txt('edit'),
                'default' => false,
            ],
        ];
    }
}
