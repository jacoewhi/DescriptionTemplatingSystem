<?php


namespace App\Console\Commands\DTS;


use App\Console\Commands\DTS\Exceptions\MissingEntryException;
use App\Console\Commands\DTS\Exceptions\MissingTemplateException;
use App\Console\Commands\DTS\Templates\SpecTemplateEntry;
use App\Console\Commands\DTS\Templates\Template;
use App\Console\Commands\DTS\ValueConversion\SpecValueConverter;
use App\Models\DoSupply\DescriptionSpec;
use App\Models\Store\Products\Content\TechnicalSpec;

class Renderer
{

	public const AUTOMATIONSTOP = "astop";
	public const DOSUPPLY = "dosupply";
	private int $id;
	private string $partNumber;
	private Template $template;
	private SpecValueConverter $valueConverter;
	private $technicalSpecs;
	private bool $usedAdditionally;
	private bool $usedAlso;
	private bool $usedInAddition;
	private int $usedCount;
	private bool $usedFurthermore;
	private string $site;

	/**
	 * Renderer constructor.
	 * @param int $id
	 * @param string $partNumber
	 * @param Template $template
	 * @param SpecValueConverter $valueConverter
	 * @param string $site
	 */
	public function __construct(int $id, string $partNumber, Template $template, SpecValueConverter $valueConverter, string $site) {
		$this->id = $id;
		$this->partNumber = $partNumber;
		$this->template = $template;
		$this->valueConverter = $valueConverter;
		$this->site = $site;
		if ($this->site == self::DOSUPPLY) {
			$this->technicalSpecs = DescriptionSpec::query()->where('product_id', '=', $this->id)->get();
		} elseif ($this->site == self::AUTOMATIONSTOP) {
			$this->technicalSpecs = TechnicalSpec::query()->where("product_id", "=", $this->id)->get();
		}
		$this->usedAdditionally = false;
		$this->usedAlso = false;
		$this->usedInAddition = false;
		$this->usedCount = 0;
		$this->usedFurthermore = false;
	}

	/**
	 * @return string
	 * @throws MissingEntryException
	 * @throws MissingTemplateException
	 */
	public function composeMulti(): string {
		//retrieve a random multi spec sentence from the templates
		$sentence = $this->template->getSentenceTemplates()->getRandomMultiSpecTemplate()->getTemplate();
		$patterns = [];
		$replacements = [];
		$sentence = $this->renderGlossaryTerms($sentence);
		if ($sentence == "") {
			return $this->composeSupporting();
		}
		$count = preg_match_all("/\[spec/", $sentence);
		for ($i = 0; $i < $count; $i++) {
			array_push($patterns, "[spec" . ($i + 1) . "]");
			$specTemplate = $this->template->getSpecTemplates()->getSpecTemplateByPriority();
			$renderedSpecTemplate = $this->renderSpecs($specTemplate);
			array_push($replacements, $renderedSpecTemplate);
		}
		$sentence = str_replace($patterns, $replacements, $sentence);
		return $this->renderGlossaryTerms($sentence);
	}

	/**
	 * @param string $sentence
	 * @return string
	 * @throws MissingEntryException
	 * @throws MissingTemplateException
	 */
	public function renderGlossaryTerms(string $sentence): string {
		//render special glossary
		$sentence = str_ireplace('[part number]', $this->partNumber, $sentence);
		if (preg_match("/\[spec:primary]/", $sentence)) {
			$sentence = str_replace('[spec:primary]', $this->renderSpecs($this->template->getSpecTemplates()->getPrimarySpecTemplateByPriority()), $sentence);
		}

		//render inline glossary
		preg_match_all("~\[([^\[\]]+,[^\[\]]+)]~", $sentence, $matches);
		//find out how many inline glossary replacements are in the sentence
		$count = count($matches[0]);
		//start at 1 to skip the full pattern match
		for ($i = 0; $i < $count; $i++) {
			//get each of the terms
			$terms = explode(',', $matches[1][$i]);
			//select a random term
			$term = trim($terms[array_rand($terms)]);
			//replace the selected term with the full inline glossary pattern
			if (strtolower($term) == 'blank') {
				$sentence = str_replace($matches[0][$i], " ", $sentence);
			} else {
				$sentence = str_replace($matches[0][$i], $term, $sentence);
			}
		}
		//render declared glossary
		preg_match_all('/\[(\w+)]/', $sentence, $matches, PREG_PATTERN_ORDER);
		$count = count($matches[0]);
		for ($i = 0; $i < $count; $i++) {
			$key = strtolower($matches[1][$i]);
			if ($key == "spec" || $key == "spec1" || $key == "spec2" || $key == "spec3") {
				continue;
			}
			$term = $this->template->getGlossary()->getGlossaryEntryByKey($key)->getRandomValue();
			if (($term == 'additionally' || $term == 'also' || $term == 'in addition') && $this->usedCount >= 2) {
				return "";
			}
			while (
				($term == 'additionally' && $this->usedAdditionally) ||
				($term == 'also' && $this->usedAlso) ||
				($term == 'in addition' && $this->usedInAddition)
			) {
				$term = $this->template->getGlossary()->getGlossaryEntryByKey($key)->getRandomValue();
			}
			if ($term == 'additionally') {
				$this->usedAdditionally = true;
				$this->usedCount++;
			} elseif ($term == 'also') {
				$this->usedAlso = true;
				$this->usedCount++;
			} elseif ($term == 'in addition') {
				$this->usedInAddition = true;
				$this->usedCount++;
			}
			if ($term == 'blank') {
				$sentence = str_replace($matches[0][$i], " ", $sentence);
			} else {
				$sentence = str_replace($matches[0][$i], $term, $sentence);
			}
		}
		$sentence = preg_replace("/\s+/", " ", $sentence);
		$sentence = preg_replace("/\s,/", ",", $sentence);
		$sentence = preg_replace("~\sa\s([aeiouAEIOU])~", " an $1", $sentence);
		$hasIndex = stripos($sentence, " has ");
		$comesWithIndex = stripos($sentence, " comes with ");
		if (!empty($hasIndex)) {
			$sentence = substr($sentence, 0, $hasIndex + 4) . preg_replace("~ has ~", " ", substr($sentence, $hasIndex + 4));
		}
		if (!empty($comesWithIndex)) {
			$sentence = substr($sentence, 0, $comesWithIndex + 12) . preg_replace("~ comes with ~", " ", substr($sentence, $comesWithIndex + 12));
		}
		return ucfirst(trim($sentence));
	}

	/**
	 * @param SpecTemplateEntry $templateEntry
	 * @return string
	 * @throws MissingEntryException
	 * @throws MissingTemplateException
	 */
	public function renderSpecs(SpecTemplateEntry $templateEntry): string {
		//get the name of the highest priority spec template that has not had its spec used yet
		$name = $templateEntry->getName();
		//get the raw spec name from the value converter to check against the raw product spec name
		$unconvertedName = $this->valueConverter->getValueConversions()->getValueConversionEntryByGroup($name)->getSpecification();
		//loop through the specs
		//if the unconverted name matches and there is a value conversion for its value then retrieve it
		$value = $this->technicalSpecs->first(function ($spec) use ($unconvertedName, $name) {
			try {
//                error_log($name . "|" . $unconvertedName . "|" . $spec->spec . "|");
				//check if name matches
				if ($unconvertedName == $spec->spec) {
					//check if value has a conversion
					//throws missing entry exception if not found
					$tempValue = $this->valueConverter->getValueConversions()->getValueConversionEntry($unconvertedName, $spec->stat);
					if ($tempValue->getTemplateGroup() == $name) {
						return true;
					} else {
						return false;
					}
				} else {
					return false;
				}
			} catch (MissingEntryException $exception) {
				//move on to the next spec
				return false;
			}
		});
		if ($value == null) {
			if ($templateEntry->getType() == SpecTemplateEntry::PRIMARY_IDENTIFIER) {
				return $this->renderSpecs($this->template->getSpecTemplates()->getPrimarySpecTemplateByPriority());
			} else {
				return $this->renderSpecs($this->template->getSpecTemplates()->getSpecTemplateByPriority());
			}
		}
		try {
			$convertedValue = $this->valueConverter->getValueConversions()->getValueConversionEntry($value->spec, $value->stat)->getValueForTemplate();
//            error_log($convertedValue);
		} catch (MissingEntryException $exception) {
			if ($templateEntry->getType() == SpecTemplateEntry::PRIMARY_IDENTIFIER) {
				return $this->renderSpecs($this->template->getSpecTemplates()->getPrimarySpecTemplateByPriority());
			} else {
				return $this->renderSpecs($this->template->getSpecTemplates()->getSpecTemplateByPriority());
			}
		}
		if ($templateEntry->getType() == SpecTemplateEntry::PRIMARY_IDENTIFIER) {
			return $convertedValue;
		} else {
			return str_replace("[value]", $convertedValue, $templateEntry->getTemplate());
		}
	}

	/**
	 * @return string
	 * @throws MissingTemplateException|MissingEntryException
	 */
	public function composeSupporting(): string {
		$sentence = $this->template->getSentenceTemplates()->getRandomSupportingTemplate()->getTemplate();
		$containsFurthermore = preg_match("~Furthermore,~", $sentence);
		if ($containsFurthermore) {
			if ($this->usedFurthermore) {
				return $this->composeSupporting();
			} else {
				$this->usedFurthermore = true;
			}
		}
		$sentence = $this->renderGlossaryTerms($sentence);
		if ($sentence == "") {
			return $this->composeSupporting();
		}
		$sentence = str_replace("[spec]", $this->renderSpecs($this->template->getSpecTemplates()->getSpecTemplateByPriority()), $sentence);
		return $this->renderGlossaryTerms($sentence);
	}

	/**
	 * @return string
	 * @throws MissingTemplateException
	 * @throws MissingEntryException
	 */
	public function composeIntro(): string {
		//retrieve a random introductory sentence from the templates
		$intro = $this->template->getSentenceTemplates()->getRandomIntroductionTemplate()->getTemplate();
		try {
			//render intro glossary terms before specs to catch primary spec usages
			$intro = $this->renderGlossaryTerms($intro);
			if ($intro == "") {
				return $this->composeIntro();
			}
			//append a specification template with its value rendered
			//can use up primary specs at this point

			$intro = str_replace("[spec]", $this->renderSpecs($this->template->getSpecTemplates()->getSpecTemplateByPriority()), $intro);
		} catch (MissingEntryException | MissingTemplateException $exception) {
			if ($exception->getCode() == 1) {
				$this->template->getSpecTemplates()->resetUsedSpecs();
				return $this->composeIntro();
			} else {
				throw $exception;
			}
		}
		return $this->renderGlossaryTerms($intro);
	}

	/**
	 * @return string
	 * @throws MissingTemplateException
	 * @throws MissingEntryException
	 */
	public function generateKeyFeature(): string {
		$specTemplate = $this->template->getSpecTemplates()->getSpecTemplateByPriority();
		$keyFeature = $this->renderSpecs($specTemplate);
		return $this->renderGlossaryTerms($keyFeature);
	}

}
