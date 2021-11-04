<?php


namespace App\Console\Commands\DTS\Templates;

/**
 * Class SpecTemplateEntry
 *
 * simple object that represents a relationship between a specification and a
 * sentence template for that specification as well as the
 * priority of the specification for choosing which specs should be added to a sentence first
 *
 * @package App\Console\Commands\DTS\Templates
 */
class SpecTemplateEntry {

    /**
     * string identifier denoting that a template is for an introductory sentence
     */
    const PRIMARY_IDENTIFIER = 'primary';

    /**
     * string identifier denoting that a template is for a supporting sentence
     */
    const REGULAR_IDENTIFIER = 'regular';

    /**
     * the name of the specification associated with this template
     *
     * @var string
     */
    private string $name;

    /**
     * the phrase template that describes the specification of this instance
     *
     * @var string
     */
    private string $template;

    /**
     * The priority of this specification being chosen over other specifications in a sentence
     *
     * @var int
     */
    private int $priority;

    /**
     * Enum-like field that denotes whether a spec is an primary or regular spec
     *
     * @var string
     */
    private string $type;

    /**
     * SpecTemplateEntry constructor.
     *
     * populates the name, template, and priority fields with the given values
     *
     * @param string $name the name of this templates spec
     * @param string $template the template for the spec
     * @param int $priority the priority of the spec
     */
    public function __construct(string $name, string $template, int $priority, string $type) {
        $this->setName($name);
        $this->setTemplate($template);
        $this->setPriority($priority);
        $this->setType($type);
    }

    /**
     * sets the name of the specification for this template
     *
     * @param string $name the specification name
     */
    private function setName(string $name): void {
        $this->name = $name;
    }

    /**
     * sets the template associated with the spec
     *
     * @param string $template the template for the spec
     */
    private function setTemplate(string $template): void {
        $this->template = $template;
    }

    /**
     * sets the priority of the spec
     *
     * @param int $priority the priority of the spec
     */
    private function setPriority(int $priority): void {
        $this->priority = $priority;
    }

    /**
     * @param string $type
     */
    private function setType(string $type): void {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType(): string {
        return $this->type;
    }

    /**
     * returns the name of the specification associated with this template
     *
     * @return string the name of the spec
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * returns the template for the specification
     *
     * @return string the template for the spec
     */
    public function getTemplate(): string {
        return $this->template;
    }

    /**
     * returns the priority of the specification
     *
     * @return int the specs priority
     */
    public function getPriority(): int {
        return $this->priority;
    }


}
