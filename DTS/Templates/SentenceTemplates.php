<?php


namespace App\Console\Commands\DTS\Templates;


use App\Console\Commands\DTS\Exceptions\BadSentenceTypeException;
use App\Console\Commands\DTS\Exceptions\CouldNotLogErrorException;
use App\Console\Commands\DTS\Exceptions\MissingTemplateException;

/**
 * Class SentenceTemplates
 * @package App\Console\Commands\DTS\Templates
 */
class SentenceTemplates {

    /**
     * an array of introduction sentence template entries
     * @var SentenceTemplateEntry[]
     */
    private array $introductionTemplates;

    /**
     * an array of supporting sentence template entries
     * @var SentenceTemplateEntry[]
     */
    private array $supportingTemplates;

    /**
     * an array of multi-spec sentence template entries
     * @var SentenceTemplateEntry[]
     */
    private array $multiSpecTemplates;

    /**
     * initializes each of the sentence type template holders to an empty array
     * SentenceTemplates constructor.
     */
    public function __construct() {
        $this->setIntroductionTemplates([]);
        $this->setSupportingTemplates([]);
        $this->setMultiSpecTemplates([]);
    }

    /**
     * sets the array of introduction templates equal to the given array
     * @param SentenceTemplateEntry[] $introductionTemplates
     */
    private function setIntroductionTemplates(array $introductionTemplates): void {
        $this->introductionTemplates = $introductionTemplates;
    }

    /**
     * sets the array of supporting templates equal to the given array
     * @param SentenceTemplateEntry[] $supportingTemplates
     */
    private function setSupportingTemplates(array $supportingTemplates): void {
        $this->supportingTemplates = $supportingTemplates;
    }

    /**
     * sets the array of multi spec templates equal to the given array
     * @param SentenceTemplateEntry[] $multiSpecTemplates
     */
    private function setMultiSpecTemplates(array $multiSpecTemplates): void {
        $this->multiSpecTemplates = $multiSpecTemplates;
    }

    /**
     * adds the sentence template entry to the list of introduction templates
     * if the given template is not an introduction template then it
     * throws a BadSentenceTypeException
     * @param SentenceTemplateEntry $sentenceTemplateEntry
     * @throws BadSentenceTypeException
     */
    public function addIntroductionTemplate(SentenceTemplateEntry $sentenceTemplateEntry): void {
        if ($sentenceTemplateEntry->getType() !== SentenceTemplateEntry::INTRODUCTION_IDENTIFIER) {
            throw new BadSentenceTypeException('Expected introduction sentence, received: ' . $sentenceTemplateEntry->getType());
        }
        array_push($this->introductionTemplates, $sentenceTemplateEntry);
    }

    /**
     * adds the sentence template entry to the list of supporting templates
     * if the given template is not a supporting template then it
     * throws a BadSentenceTypeException
     * @param SentenceTemplateEntry $sentenceTemplateEntry
     * @throws BadSentenceTypeException
     */
    public function addSupportingTemplate(SentenceTemplateEntry $sentenceTemplateEntry): void {
        if ($sentenceTemplateEntry->getType() !== SentenceTemplateEntry::SUPPORTING_IDENTIFIER) {
            throw new BadSentenceTypeException('Expected supporting sentence, received: ' . $sentenceTemplateEntry->getType());
        }
        array_push($this->supportingTemplates, $sentenceTemplateEntry);
    }

    /**
     * adds the sentence template entry to the list of multi spec templates
     * if the given template is not an introduction template then it
     * throws a BadSentenceTypeException
     * @param SentenceTemplateEntry $sentenceTemplateEntry
     * @throws BadSentenceTypeException
     */
    public function addMultiSpecTemplate(SentenceTemplateEntry $sentenceTemplateEntry): void {
        if ($sentenceTemplateEntry->getType() !== SentenceTemplateEntry::MULTI_SPEC_IDENTIFIER) {
            throw new BadSentenceTypeException('Expected multi-spec sentence, received: ' . $sentenceTemplateEntry->getType());
        }
        array_push($this->multiSpecTemplates, $sentenceTemplateEntry);
    }

    /**
     * @return SentenceTemplateEntry
     * @throws MissingTemplateException
     */
    public function getRandomIntroductionTemplate(): SentenceTemplateEntry {

        if (empty($this->introductionTemplates)) {
            throw new MissingTemplateException("ran out of introduction templates");
        } else {
            $index = array_rand($this->introductionTemplates);
            $introductionTemplate = $this->introductionTemplates[$index];
        }
        return $introductionTemplate;
    }

    /**
     * @return SentenceTemplateEntry
     * @throws MissingTemplateException
     */
    public function getRandomSupportingTemplate(): SentenceTemplateEntry {

        if (empty($this->supportingTemplates)) {
            throw new MissingTemplateException("ran out of supporting templates");
        } else {
//            dump($this->supportingTemplates);
            $index = array_rand($this->supportingTemplates);
//            error_log($index);
            $supportingTemplate = $this->supportingTemplates[$index];
        }
        return $supportingTemplate;
    }

    /**
     * @return SentenceTemplateEntry
     * @throws MissingTemplateException
     */
    public function getRandomMultiSpecTemplate(): SentenceTemplateEntry {

        if (empty($this->multiSpecTemplates)) {
            throw new MissingTemplateException("ran out of multi-spec templates");
        } else {
            $index = array_rand($this->multiSpecTemplates);
            $multiSpecTemplate = $this->multiSpecTemplates[$index];
        }
        return $multiSpecTemplate;
    }

}
