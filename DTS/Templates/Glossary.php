<?php


namespace App\Console\Commands\DTS\Templates;

use App\Console\Commands\DTS\Exceptions\MissingEntryException;

/**
 * Class Glossary
 *
 * manages the gloassary entries and provides methods for adding to and retrieving from it
 *
 * @package App\Console\Commands\DTS\Templates
 */
class Glossary {

    /**
     * @var GlossaryEntry[] an array of entries in the glossary
     */
    private array $glossaryEntries;

    /**
     * Glossary constructor.
     *
     * The glossary object is designed to be empty initially and then
     * have glossary entries added to it one by one
     */
    public function __construct() {
        $this->glossaryEntries = [];
    }

    /**
     *
     * adds a glossary entry to the glossary
     *
     * @param GlossaryEntry $glossaryEntry
     */
    public function addGlossaryEntry(GlossaryEntry $glossaryEntry): void {
        $this->glossaryEntries[$glossaryEntry->getKey()] = $glossaryEntry;
    }

    /**
     * returns the glossary entry from the glossary that matches the given key
     *
     * @param string $key
     * @return GlossaryEntry
     * @throws MissingEntryException
     */
    public function getGlossaryEntryByKey(string $key): GlossaryEntry {
        if (!isset($this->glossaryEntries[$key])) {
            throw new MissingEntryException("could not find a glossary item with key: " . $key);
        }
        return $this->glossaryEntries[$key];
    }


}
