<?php


namespace App\Console\Commands\DTS\Templates;

/**
 * Class SentenceTemplateEntry
 *
 * simple object that represents a template for a sentence that is designed to be paired with a specification template.
 *
 * @package App\Console\Commands\DTS\Templates
 */
class SentenceTemplateEntry {

    /**
     * string identifier denoting that a template is for an introductory sentence
     */
    const INTRODUCTION_IDENTIFIER = 'introduction';
    /**
     * string identifier denoting that a template is for a supporting sentence
     */
    const SUPPORTING_IDENTIFIER = 'Secondary';
    /**
     * string identifier denoting that a template is for a multi-spec sentence
     */
    const MULTI_SPEC_IDENTIFIER = 'Multi-Spec';

    /**
     * Enum-like field that denotes whether a sentence is an introductory or supporting sentence
     *
     * @var string
     */
    private string $type;

    /**
     * the template of this instance
     *
     * @var string
     */
    private string $template;

    /**
     * SentenceTemplateEntry constructor.
     *
     * populates the type and template fields of this object using the provided type and template
     *
     * @param string $type the type of sentence
     * @param string $template the sentence template
     */
    public function __construct(string $type, string $template) {
        $this->setType($type);
        $this->setTemplate($template);
    }

    /**
     * sets this instances type to the provided value
     *
     * @param string $type the type of sentence
     */
    private function setType(string $type): void {
        $this->type = $type;
    }

    /**
     * sets this instances template to the provided value
     *
     * @param string $template the sentence template
     */
    private function setTemplate(string $template): void {
        $this->template = $template;
    }

    /**
     * returns this templates sentence type
     *
     * @return string the type of sentence
     */
    public function getType(): string {
        return $this->type;
    }

    /**
     * returns the template of this instance
     *
     * @return string the sentence template
     */
    public function getTemplate(): string {
        return $this->template;
    }

}
