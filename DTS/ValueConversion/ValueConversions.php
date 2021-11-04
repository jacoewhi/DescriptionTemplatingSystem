<?php


namespace App\Console\Commands\DTS\ValueConversion;


use App\Console\Commands\DTS\Exceptions\MissingEntryException;

/**
 * Class ValueConversions
 * @package App\Console\Commands\DTS\ValueConversion
 */
class ValueConversions {

    /**
     * @var ValueConversionEntry[]
     */
    private array $valueConversionEntries;

    /**
     * @var ValueConversionEntry[]
     */
    private array $templateGroups;

    /**
     * ValueConversions constructor.
     */
    public function __construct() {
        $this->valueConversionEntries = [];
        $this->templateGroups = [];
    }

    /**
     * @param ValueConversionEntry $valueConversionEntry
     */
    public function addValueConversionEntry(ValueConversionEntry $valueConversionEntry): void {
        $this->valueConversionEntries[$valueConversionEntry->getSpecification() . $valueConversionEntry->getValue()] = $valueConversionEntry;
        $this->templateGroups[$valueConversionEntry->getTemplateGroup()] = $valueConversionEntry;
    }

    /**
     * @param string $specification
     * @param string $value
     * @return ValueConversionEntry
     * @throws MissingEntryException
     */
    public function getValueConversionEntry(string $specification, string $value): ValueConversionEntry {
        if (!isset($this->valueConversionEntries[$specification . $value])) {
            throw new MissingEntryException("could not find values for specification and value: " . $specification . ":" . $value);
        }
        return $this->valueConversionEntries[$specification . $value];
    }

    /**
     * @param string $templateGroup
     * @return ValueConversionEntry
     * @throws MissingEntryException
     */
    public function getValueConversionEntryByGroup(string $templateGroup): ValueConversionEntry {
        if (!isset($this->templateGroups[$templateGroup])) {
            throw new MissingEntryException("could not find template group for " . $templateGroup);
        }
        return $this->templateGroups[$templateGroup];
    }

}
