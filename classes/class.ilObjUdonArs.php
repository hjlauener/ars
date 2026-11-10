<?php

declare(strict_types=1);

/**
 * Application class of the Udon ARS repository object: a presentation, i.e.
 * a sequence of questions, with a fixed six-digit participation code, a
 * colour palette and the result mode (results shown at once or after
 * release by the presenter).
 */
class ilObjUdonArs extends ilObjectPlugin
{
    private string $join_code = '';
    private string $palette = '';
    private string $result_mode = ilUdaService::RESULTS_AUTO;

    protected function initType(): void
    {
        $this->setType(ilUdonArsPlugin::ID);
    }

    private function repo(): ilUdaRepository
    {
        return ilUdaRepository::create();
    }

    protected function doCreate(bool $clone_mode = false): void
    {
        $this->palette = ilUdaConfig::defaultPalette();
        $this->repo()->createData($this->getId(), $this->palette, $this->result_mode);
        $this->doRead();
    }

    protected function doRead(): void
    {
        $data = $this->repo()->getData($this->getId());
        if ($data === null) {
            // object created before the data table existed
            $this->repo()->createData($this->getId(), ilUdaConfig::defaultPalette(), ilUdaService::RESULTS_AUTO);
            $data = $this->repo()->getData($this->getId());
        }
        $this->join_code = (string) ($data['join_code'] ?? '');
        $this->palette = ilUdaPalettes::resolve((string) ($data['palette'] ?? ''));
        $this->result_mode = ($data['result_mode'] ?? '') === ilUdaService::RESULTS_RELEASE
            ? ilUdaService::RESULTS_RELEASE : ilUdaService::RESULTS_AUTO;
    }

    protected function doUpdate(): void
    {
        $this->repo()->updateData($this->getId(), $this->palette, $this->result_mode);
    }

    protected function doDelete(): void
    {
        $this->repo()->deleteObject($this->getId());
    }

    /**
     * Copy: settings and questions, not the sessions and answers. The copy
     * gets its own participation code.
     */
    protected function doCloneObject(ilObject2 $new_obj, int $a_target_id, ?int $a_copy_id = null): void
    {
        if (!$new_obj instanceof self) {
            return;
        }
        $new_obj->setPalette($this->palette);
        $new_obj->setResultMode($this->result_mode);
        $new_obj->update();
        $repo = $this->repo();
        foreach ($repo->getQuestions($this->getId()) as $question) {
            $repo->addQuestion($new_obj->getId(), $question);
        }
    }

    public function getJoinCode(): string
    {
        return $this->join_code;
    }

    public function renewJoinCode(): void
    {
        $this->join_code = $this->repo()->renewJoinCode($this->getId());
    }

    public function getPalette(): string
    {
        return $this->palette;
    }

    public function setPalette(string $palette): void
    {
        $this->palette = ilUdaPalettes::resolve($palette);
    }

    public function getResultMode(): string
    {
        return $this->result_mode;
    }

    public function setResultMode(string $mode): void
    {
        $this->result_mode = $mode === ilUdaService::RESULTS_RELEASE ? ilUdaService::RESULTS_RELEASE : ilUdaService::RESULTS_AUTO;
    }
}
