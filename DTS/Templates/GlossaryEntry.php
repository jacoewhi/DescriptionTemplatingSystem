<?php


namespace App\Console\Commands\DTS\Templates;

/**
 * Class GlossaryEntry
 *
 * Represents an entry in the glossary which has a key that is to be associated with one of
 * a number of possible different values chosen at random.
 *
 * @package App\Console\Commands\DTS\Templates
 */
class GlossaryEntry {

    /**
     * the key for this Entry
     *
     * @var string
     */
    private string $key;

    /**
     * the list of string alternates associated with this Entry's key
     *
     * @var string[]
     */
    private array $values;

    /**
     * GlossaryEntry constructor.
     * initializes this object with the given key and an empty array for values
     *
     * @param string $key
     */
    public function __construct(string $key) {
        $this->setKey($key);
        $this->setValues([]);
    }

    /**
     * sets the Key of this Entry
     *
     * @param string $key the key to be set
     */
    private function setKey(string $key): void {
        $this->key = $key;
    }

    /**
     * sets this Entry's list of values
     *
     * @param string[] $values a list of string alternates to replace this Entry's key
     */
    private function setValues(array $values): void {
        $this->values = $values;
    }

    /**
     * returns the key of this Entry
     *
     * @return string the key of this Entry
     */
    public function getKey(): string {
        return $this->key;
    }

    /**
     * returns a random value from this Entry's list of values to replace the key
     *
     * @return string the randomly selected value for this Entry's key
     */
    public function getRandomValue(): string {
        $index = array_rand($this->values);
        return $this->values[$index];
    }

    /**
     * adds a value alternate to this Entry's list of possible values to replace the key
     *
     * @param string $value the value to be added to the Entry
     */
    public function addValue(string $value): void {
        array_push($this->values, $value);
    }

}
