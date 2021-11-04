<?php


namespace App\Console\Commands\DTS\Templates;


use App\Console\Commands\DTS\Exceptions\CouldNotLogErrorException;
use App\Console\Commands\DTS\Exceptions\FileNotFoundException;

/**
 * Class Template
 * @package App\Console\Commands\DTS\Templates
 */
class Template {

    /**
     *
     */
    const GLOSSARY_IDENTIFIER = 'Glossary';
    /**
     *
     */
    const SENTENCE_TEMPLATE_IDENTIFIER = 'Sentence Templates';
    /**
     *
     */
    const SPEC_TEMPLATE_IDENTIFIER = 'Specifications';

    /**
     * @var Glossary
     */
    private Glossary $glossary;

    /**
     * @var SentenceTemplates
     */
    private SentenceTemplates $sentenceTemplates;

    /**
     * @var SpecTemplates
     */
    private SpecTemplates $specTemplates;

    /**
     * Template constructor.
     * @param string $filePath
     * @throws CouldNotLogErrorException
     * @throws FileNotFoundException
     */
    public function __construct(string $filePath) {
        self::getTemplateFromCSV($filePath);
    }

    /**
     * @param string $filePath
     * @throws CouldNotLogErrorException
     * @throws FileNotFoundException
     */
    private function getTemplateFromCSV(string $filePath): void {
        //attempt to open file stream
        $csv = fopen($filePath, 'r');
        //if file open fails throw a fatal error
        if ($csv === false) {
            throw new FileNotFoundException("template file open failure");
        }
        error_log("template file found");
        //get the first line of the csv
        //this should be one of the section headers
        $line = fgetcsv($csv);
        //loop through each section
        while ($line) {
            //if its a glossary section
            if (trim($line[0]) == self::GLOSSARY_IDENTIFIER) {

                //remove the headers
                fgetcsv($csv);
                //get the first key and value
                $line = fgetcsv($csv);
                //create the Glossary object
                $glossary = new Glossary();
                //loop through the keys and values as long as the line is not a section header
                while ($line &&
                    trim($line[0]) != self::SENTENCE_TEMPLATE_IDENTIFIER &&
                    trim($line[0]) != self::SPEC_TEMPLATE_IDENTIFIER) {

                    //cleans up the key and extracts the values into an array
                    $key = trim($line[0]);
                    $values = explode(',', $line[1]);
                    //checks that both the key and its values are not empty
                    if (!empty($key) && !empty($values)) {

                        //creates a new GlossaryEntry using the key
                        $glossaryEntry = new GlossaryEntry($key);
                        //loops through the values and adds them to the GlossaryEntry
                        foreach ($values as $value) {

                            $glossaryEntry->addValue(trim($value));
                        }
                        //adds the GlossaryEntry to the Glossary
                        $glossary->addGlossaryEntry($glossaryEntry);
                    }
                    $line = fgetcsv($csv);
                }
                $this->setGlossary($glossary);
                error_log("glossary loaded");
                //if its a sentence template section
            } elseif (trim($line[0]) == self::SENTENCE_TEMPLATE_IDENTIFIER) {

                //remove the headers
                fgetcsv($csv);
                //get the first type and sentence template
                $line = fgetcsv($csv);
                //create the SentenceTemplates object
                $sentenceTemplates = new SentenceTemplates();
                //loop through the types and templates as long as the line is not a section header
                while ($line &&
                    trim($line[0]) != self::GLOSSARY_IDENTIFIER &&
                    trim($line[0]) != self::SPEC_TEMPLATE_IDENTIFIER) {

                    //cleans up the type and template
                    $type = trim($line[0]);
                    $template = trim($line[1]);
                    //checks that both the type and template are not empty
                    if (!empty($type) && !empty($template)) {

                        //creates a new SentenceTemplateEntry
                        $sentenceTemplateEntry = new SentenceTemplateEntry($type, $template);
                        //check the sentence type and then add to SentenceTemplates object
                        if ($type == SentenceTemplateEntry::INTRODUCTION_IDENTIFIER) {

                            //add to introduction sentences
                            $sentenceTemplates->addIntroductionTemplate($sentenceTemplateEntry);
                        } elseif ($type == SentenceTemplateEntry::SUPPORTING_IDENTIFIER) {

                            //add to supporting sentences
                            $sentenceTemplates->addSupportingTemplate($sentenceTemplateEntry);
                        } else {

                            //add to multi spec sentences

                            //if it is neither an introduction, supporting, or multi-spec sentence
                            //throws a BadSentenceTypeException
                            $sentenceTemplates->addMultiSpecTemplate($sentenceTemplateEntry);
                        }
                    }
                    $line = fgetcsv($csv);
                }
                $this->setSentenceTemplates($sentenceTemplates);
                error_log("sentence templates loaded");
            } elseif (trim($line[0]) == self::SPEC_TEMPLATE_IDENTIFIER) {

                //remove the headers
                fgetcsv($csv);
                //get the first spec, template, and priority
                $line = fgetcsv($csv);
                //create the SpecTemplates object
                $specTemplates = new SpecTemplates();
                //loop through the specs, templates, and priorities as long as the line is not a section header
                while ($line &&
                    trim($line[0]) != self::GLOSSARY_IDENTIFIER &&
                    trim($line[0]) != self::SENTENCE_TEMPLATE_IDENTIFIER) {

                    //cleans up the spec, template, and priority
                    $name = trim($line[0]);
                    $template = trim($line[1]);
                    $priority = trim($line[2]);
                    //checks that the spec and template are not empty
                    if (!empty($name) && !empty($template)) {
                        //check if it is a primary spec
                        if (preg_match("/(P)(\d+)?/", $priority, $matches) === 1) {
                            //set the priority equal to the digit value if it exists or 0 if not specified
                            if (isset($matches[2])) {
                                $priority = $matches[2];
                            } else {
                                //using int max instead of zero to help with sorting
                                $priority = PHP_INT_MAX - 1;
                            }
                            //create a new entry and add to the primary spec templates list
                            $specTemplates->addPrimarySpecTemplate(new SpecTemplateEntry($name, $template, $priority, SpecTemplateEntry::PRIMARY_IDENTIFIER));
                            $specTemplates->addSpecTemplate(new SpecTemplateEntry($name, $template, $priority, SpecTemplateEntry::REGULAR_IDENTIFIER));
                        } else {
                            //set priority equal to 0 if not otherwise specified
                            if (empty($priority) || $priority == 0) {
                                //using int max instead of zero to help with sorting
                                $priority = PHP_INT_MAX;
                            }
                            //create a new entry and add to the spec templates list
                            $specTemplate = new SpecTemplateEntry($name, $template, $priority, SpecTemplateEntry::REGULAR_IDENTIFIER);
                            $specTemplates->addSpecTemplate($specTemplate);
                        }
                    }
                    $line = fgetcsv($csv);
                }
                $this->setSpecTemplates($specTemplates);
                error_log("spec templates loaded");
            } else {
                //if after a section the line is anything other than one of the section identifiers then exit
                break;
            }
        }
        fclose($csv);
    }

    /**
     * @param Glossary $glossary
     */
    private function setGlossary(Glossary $glossary): void {
        $this->glossary = $glossary;
    }

    /**
     * @param SentenceTemplates $sentenceTemplates
     */
    private function setSentenceTemplates(SentenceTemplates $sentenceTemplates): void {
        $this->sentenceTemplates = $sentenceTemplates;
    }

    /**
     * @param SpecTemplates $specTemplates
     */
    private function setSpecTemplates(SpecTemplates $specTemplates): void {
        $this->specTemplates = $specTemplates;
    }

    /**
     * @return Glossary
     */
    public function getGlossary(): Glossary {
        return $this->glossary;
    }

    /**
     * @return SentenceTemplates
     */
    public function getSentenceTemplates(): SentenceTemplates {
        return $this->sentenceTemplates;
    }

    /**
     * @return SpecTemplates
     */
    public function getSpecTemplates(): SpecTemplates {
        return $this->specTemplates;
    }


}
