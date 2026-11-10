<?php

declare(strict_types=1);

/**
 * Configuration of the plugin (Administration › Erweiterungen › Plugins ›
 * UdonArs › Konfigurieren). Only what is needed: participation without
 * login, available question types, standard palette, deleting old results
 * and an optional short address for participation. Everything else follows
 * from the ILIAS permissions.
 *
 * @ilCtrl_IsCalledBy ilUdonArsConfigGUI: ilObjComponentSettingsGUI
 */
class ilUdonArsConfigGUI extends ilPluginConfigGUI
{
    public function performCommand(string $cmd): void
    {
        switch ($cmd) {
            case 'save':
                $this->save();
                break;
            case 'configure':
            default:
                $this->configure();
                break;
        }
    }

    private function configure(?ilPropertyFormGUI $form = null): void
    {
        global $DIC;
        if ($form === null) {
            $form = $this->initForm();
            $form->setValuesByArray([
                'anonymous' => ilUdaConfig::anonymousAllowed(),
                'types' => ilUdaConfig::enabledTypes(),
                'palette' => ilUdaConfig::defaultPalette(),
                'retention' => ilUdaConfig::retentionDays(),
                'short_url' => ilUdaConfig::shortUrl(),
            ]);
        }
        $DIC->ui()->mainTemplate()->setContent($form->getHTML());
    }

    private function save(): void
    {
        global $DIC;
        $form = $this->initForm();
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->configure($form);
            return;
        }
        $types = $form->getInput('types');
        $short_url = trim((string) $form->getInput('short_url'));
        if ($short_url !== '' && !preg_match('#^https?://\S+$#i', $short_url)) {
            $form->getItemByPostVar('short_url')->setAlert($this->txt('config_short_url_invalid'));
            $form->setValuesByPost();
            $this->configure($form);
            return;
        }
        ilUdaConfig::save(
            (bool) $form->getInput('anonymous'),
            is_array($types) ? array_map('strval', $types) : [],
            (string) $form->getInput('palette'),
            (int) $form->getInput('retention'),
            $short_url
        );
        $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->txt('config_saved'), true);
        $DIC->ctrl()->redirect($this, 'configure');
    }

    private function initForm(): ilPropertyFormGUI
    {
        global $DIC;
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->txt('config_title'));
        $form->setFormAction($DIC->ctrl()->getFormAction($this, 'save'));

        $anonymous = new ilCheckboxInputGUI($this->txt('config_anonymous'), 'anonymous');
        $anonymous->setInfo($this->txt('config_anonymous_info'));
        $form->addItem($anonymous);

        $types = new ilCheckboxGroupInputGUI($this->txt('config_types'), 'types');
        $types->setInfo($this->txt('config_types_info'));
        foreach (ilUdaConfig::TYPES as $type) {
            $option = new ilCheckboxOption($this->txt('ui_type_' . $type), $type);
            $option->setInfo($this->txt('ui_type_' . $type . '_desc'));
            $types->addOption($option);
        }
        $form->addItem($types);

        $palette = new ilRadioGroupInputGUI($this->txt('config_palette'), 'palette');
        $palette->setInfo($this->txt('config_palette_info'));
        foreach (ilUdaPalettes::IDS as $id) {
            $option = new ilRadioOption($this->txt('palette_' . $id), $id);
            $option->setInfo($this->txt('palette_' . $id . '_info') . ilUdaPalettes::swatches($id));
            $palette->addOption($option);
        }
        $form->addItem($palette);

        $retention = new ilNumberInputGUI($this->txt('config_retention'), 'retention');
        $retention->setInfo($this->txt('config_retention_info'));
        $retention->setMinValue(0);
        $retention->setMaxValue(3650);
        $retention->setDecimals(0);
        $retention->setSize(5);
        $retention->setSuffix($this->txt('config_days'));
        $form->addItem($retention);

        $short_url = new ilTextInputGUI($this->txt('config_short_url'), 'short_url');
        $short_url->setInfo($this->txt('config_short_url_info'));
        $short_url->setMaxLength(200);
        $form->addItem($short_url);

        $form->addCommandButton('save', $DIC->language()->txt('save'));
        return $form;
    }

    private function txt(string $key): string
    {
        return $this->getPluginObject()?->txt($key) ?? $key;
    }
}
