<?php


namespace App\Console\Commands\DTS\ValueConversion;

/**
 * Class ValueConversionEntry
 * @package App\Console\Commands\DTS\ValueConversion
 */
class ValueConversionEntry {

    /**
     * the specifications name
     *
     * @var string
     */
    private string $specification;

    /**
     * the value of the specification as defined by the database connection
     *
     * @var string
     */
    private string $value;

    /**
     * a list of potential cleaned versions of the value that are ready for rendering into a SpecTemplate
     *
     * @var string[]
     */
    private array $valueConversions;
    /**
     * signals to the Renderer which templates to use for this specification
     *
     * @var string
     */
    private string $templateGroup;

    /**
     * ValueConversionEntry constructor.
     *
     * populates the name of the specification and the value of the specification and initializes the
     * value conversions to an empty array
     *
     * @param string $specification the name of the spec
     * @param string $value the vlaue of the spec
     */
    public function __construct(string $specification, string $value, string $templateGroup) {
        $this->setSpecification($specification);
        $this->setValue($value);
        $this->setValueConversions([]);
        $this->setTemplateGroup($templateGroup);
    }

    /**
     * sets specifications name to the given value
     *
     * @param string $specification the name of the spec
     */
    private function setSpecification(string $specification): void {
        $this->specification = $specification;
    }

    /**
     * sets the specifications value to the given value
     *
     * @param string $value the value of the specification
     */
    private function setValue(string $value): void {
        $this->value = $value;
    }

    /**
     * sets the array of converted values alternates to the given array
     *
     * @param string[] $valueConversions
     */
    private function setValueConversions(array $valueConversions): void {
        $this->valueConversions = $valueConversions;
    }

    /**
     * @param string $templateGroup
     */
    private function setTemplateGroup(string $templateGroup): void {
        $this->templateGroup = $templateGroup;
    }

    /**
     * @return string
     */
    public function getTemplateGroup(): string {
        return $this->templateGroup;
    }

    /**
     * returns the name of the spec
     *
     * @return string the name of the spec
     */
    public function getSpecification(): string {
        return $this->specification;
    }

    /**
     * returns the value of the spec
     *
     * @return string the value of the spec
     */
    public function getValue(): string {
        return $this->value;
    }

    /**
     * returns a randomly chosen value from the list of possible cleaned values
     * returns the raw value if no cleaned values are present
     *
     * @return string
     */
    public function getValueForTemplate(): string {
        if (empty($this->valueConversions)) {
            return $this->value;
        } else {
            $index = array_rand($this->valueConversions);
            return $this->valueConversions[$index];
        }
    }

    /**
     * adds a cleaned value alternate to the list of value conversions
     *
     * @param string $valueConversion
     */
    public function addValueConversionString(string $valueConversion): void {
        array_push($this->valueConversions, $valueConversion);
    }

}
