<?php


namespace App\Console\Commands\DTS\Templates;

use App\Console\Commands\DTS\Exceptions\MissingEntryException;
use App\Console\Commands\DTS\Exceptions\MissingTemplateException;

/**
 * Class SpecTemplates
 * @package App\Console\Commands\DTS\Templates
 */
class SpecTemplates {

    /**
     * @var SpecTemplateEntry[]
     */
    private array $specTemplates;

    /**
     * @var bool
     */
    private bool $sorted;

    /**
     * @var SpecTemplateEntry[]
     */
    private array $primarySpecTemplates;
    /**
     * @var bool
     */
    private bool $primarySorted;

    /**
     * this includes both primary and non primary specs
     *
     * @var string[]
     */
    private array $usedSpecs;

    /**
     * SpecTemplates constructor.
     */
    public function __construct() {
        $this->setSpecTemplates([]);
        $this->setPrimarySpecTemplates([]);
        $this->resetUsedSpecs();
    }

    /**
     * @param SpecTemplateEntry[] $specTemplates
     */
    private function setSpecTemplates(array $specTemplates): void {
        $this->specTemplates = $specTemplates;
    }

    /**
     * @param SpecTemplateEntry[] $primarySpecTemplates
     */
    private function setPrimarySpecTemplates(array $primarySpecTemplates): void {
        $this->primarySpecTemplates = $primarySpecTemplates;
    }

    /**
     *
     */
    public function resetUsedSpecs() {
        $this->usedSpecs = [];
    }

    public function shuffleSpecs() {
        shuffle($this->specTemplates);
        $this->setSorted(false);
        shuffle($this->primarySpecTemplates);
        $this->setPrimarySorted(false);
    }

    /**
     * @param bool $sorted
     */
    private function setSorted(bool $sorted): void {
        $this->sorted = $sorted;
    }

    /**
     * @param bool $primarySorted
     */
    private function setPrimarySorted(bool $primarySorted): void {
        $this->primarySorted = $primarySorted;
    }

    /**
     * @param SpecTemplateEntry $specTemplateEntry
     */
    public function addSpecTemplate(SpecTemplateEntry $specTemplateEntry): void {
        array_push($this->specTemplates, $specTemplateEntry);
        $this->setSorted(false);
    }

    /**
     * @param SpecTemplateEntry $specTemplateEntry
     */
    public function addPrimarySpecTemplate(SpecTemplateEntry $specTemplateEntry): void {
        array_push($this->primarySpecTemplates, $specTemplateEntry);
        $this->setPrimarySorted(false);
    }

    /**
     * @return SpecTemplateEntry
     * @throws MissingTemplateException
     */
    public function getSpecTemplateByPriority(): SpecTemplateEntry {

        if (empty($this->specTemplates)) {
            throw new MissingTemplateException("ran out of spec templates");
        } else {
            if (!$this->sorted) {
                usort($this->specTemplates, fn($a, $b) => $a->getPriority() - $b->getPriority());
                $this->setSorted(true);
            }
            $specTemplate = null;
            $count = 0;
            $success = false;
            while (!empty($this->specTemplates[$count])) {
                $specTemplate = $this->specTemplates[$count];
                if (array_search($specTemplate->getName(), $this->usedSpecs) === false) {
                    $this->addUsedSpecs($specTemplate->getName());
                    $success = true;
                    break;
                }
                $count++;
            }
            if (!$success) {
                throw new MissingTemplateException("ran out of spec templates");
            }
        }
        return $specTemplate;
    }

    /**
     * @param string $name
     */
    private function addUsedSpecs(string $name): void {
        array_push($this->usedSpecs, $name);
    }

    /**
     * @return SpecTemplateEntry
     * @throws MissingTemplateException
     */
    public function getPrimarySpecTemplateByPriority(): SpecTemplateEntry {

        if (empty($this->primarySpecTemplates)) {
            throw new MissingTemplateException("ran out of primary spec templates", 1);
        } else {
            if (!$this->primarySorted) {
                usort($this->primarySpecTemplates, fn($a, $b) => $a->getPriority() - $b->getPriority());
                $this->setPrimarySorted(true);
            }
            $specTemplate = null;
            $count = 0;
            $success = false;
//            dump($this->usedSpecs);
            while (!empty($this->primarySpecTemplates[$count])) {
                $specTemplate = $this->primarySpecTemplates[$count];
                if (array_search($specTemplate->getName(), $this->usedSpecs) === false) {
                    $this->addUsedSpecs($specTemplate->getName());
                    $success = true;
                    break;
                }
                $count++;
            }
            if (!$success) {
                throw new MissingTemplateException("ran out of primary spec templates", 1);
            }
        }
        return $specTemplate;
    }

}
