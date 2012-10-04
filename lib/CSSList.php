<?php

/**
* A CSSList is the most generic container available. Its contents include CSSRuleSet as well as other CSSList objects.
* Also, it may contain CSSImport and CSSCharset objects stemming from @-rules.
*/
abstract class CSSList {
	private $aContents;

	public function __construct() {
		$this->aContents = array();
	}

	public function append($oItem) {
		$this->aContents[] = $oItem;
	}

	/**
	* Removes an item from the CSS list.
	* @param CSSRuleSet|CSSImport|CSSCharset|CSSList $oItemToRemove May be a CSSRuleSet (most likely a CSSDeclarationBlock), a CSSImport, a CSSCharset or another CSSList (most likely a CSSMediaQuery)
	*/
	public function remove($oItemToRemove) {
		$iKey = array_search($oItemToRemove, $this->aContents, true);
		if($iKey !== false) {
			unset($this->aContents[$iKey]);
		}
	}

	public function removeDeclarationBlockBySelector($mSelector, $bRemoveAll = false) {
		if($mSelector instanceof CSSDeclarationBlock) {
			$mSelector = $mSelector->getSelectors();
		}
		if(!is_array($mSelector)) {
			$mSelector = explode(',', $mSelector);
		}
		foreach($mSelector as $iKey => &$mSel) {
			if(!($mSel instanceof CSSSelector)) {
				$mSel = new CSSSelector($mSel);
			}
		}
		foreach($this->aContents as $iKey => $mItem) {
			if(!($mItem instanceof CSSDeclarationBlock)) {
				continue;
			}
			if($mItem->getSelectors() == $mSelector) {
				unset($this->aContents[$iKey]);
				if(!$bRemoveAll) {
					return;
				}
			}
		}
	}

	public function __toString() {
		$sResult = '';
		foreach($this->aContents as $oContent) {
			$sResult .= $oContent->__toString();
		}
		return $sResult;
	}
	
	public function getContents() {
		return $this->aContents;
	}
	
	protected function allDeclarationBlocks(&$aResult) {
		foreach($this->aContents as $mContent) {
			if($mContent instanceof CSSDeclarationBlock) {
				$aResult[] = $mContent;
			} else if($mContent instanceof CSSList) {
				$mContent->allDeclarationBlocks($aResult);
			}
		}
	}
	
	protected function allRuleSets(&$aResult) {
		foreach($this->aContents as $mContent) {
			if($mContent instanceof CSSRuleSet) {
				$aResult[] = $mContent;
			} else if($mContent instanceof CSSList) {
				$mContent->allRuleSets($aResult);
			}
		}
	}
	
	protected function allValues($oElement, &$aResult, $sSearchString = null, $bSearchInFunctionArguments = false) {
		if($oElement instanceof CSSList) {
			foreach($oElement->getContents() as $oContent) {
				$this->allValues($oContent, $aResult, $sSearchString, $bSearchInFunctionArguments);
			}
		} else if($oElement instanceof CSSRuleSet) {
			foreach($oElement->getRules($sSearchString) as $oRule) {
				$this->allValues($oRule, $aResult, $sSearchString, $bSearchInFunctionArguments);
			}
		} else if($oElement instanceof CSSRule) {
			$this->allValues($oElement->getValue(), $aResult, $sSearchString, $bSearchInFunctionArguments);
		} else if($oElement instanceof CSSValueList) {
			if($bSearchInFunctionArguments || !($oElement instanceof CSSFunction)) {
				foreach($oElement->getListComponents() as $mComponent) {
					$this->allValues($mComponent, $aResult, $sSearchString, $bSearchInFunctionArguments);
				}
			}
		} else {
			//Non-List CSSValue or String (CSS identifier)
			$aResult[] = $oElement;
		}
	}

    /**
     * For all font-face declarations, return an array with the font name as the key
     * and the src as the value.
     *
     * Note: Added specificially for Metrodigi use case.
     *
     * @return array
     */
    public function retrieveFontNameToSrcMapFromFontFamilies()
    {
        $embededFonts = array();
        foreach($this->getAllRuleSets() as $oRuleSet)
        {
            if(($oRuleSet instanceof CSSAtRule) && strtolower($oRuleSet->getType()) == 'font-face')
            {
                $fontFamily = "";$fontSrc = "";
                $fam = $oRuleSet->getRules('font-family');
                $src = $oRuleSet->getRules('src');
                if(isset($fam['font-family']))
                {
                    $fontFamily = (string) $fam['font-family']->getValue();
                }
                if(isset($src['src']))
                {
                    $fontSrc = (string) $src['src']->getValue();
                    //parse out the url
                    $urlMatches = array();
                    preg_match_all('/url\([\'"]{1}(.*?)[\'"]{1}\)/', $fontSrc, $urlMatches,PREG_SET_ORDER);
                    if(isset($urlMatches[0]) && isset($urlMatches[0][1]))
                    {
                        $fontSrc = $urlMatches[0][1];
                    }
                }
                if(!empty($fontFamily))
                {
                    $embededFonts[$fontFamily] = $fontSrc;
                }
            }
        }
        return $embededFonts;
    }

    /**
     * Retrieve a selector by id or name.
     *
     * @param $selector
     * @return CSSList
     */
    public function getRuleBySelector($selector)
    {
        foreach($this->getAllDeclarationBlocks() as $oBlock)
        {
            foreach($oBlock->getSelectors() as $oSelector)
            {
                if($oSelector->getSelector() == $selector)
                {
                    return $oBlock;
                }
            }
        }

        return FALSE;
    }

	protected function allSelectors(&$aResult, $sSpecificitySearch = null) {
		foreach($this->getAllDeclarationBlocks() as $oBlock) {
			foreach($oBlock->getSelectors() as $oSelector) {
				if($sSpecificitySearch === null) {
					$aResult[] = $oSelector;
				} else {
					$sComparison = "\$bRes = {$oSelector->getSpecificity()} $sSpecificitySearch;";
					eval($sComparison);
					if($bRes) {
						$aResult[] = $oSelector;
					}
				}
			}
		}
	}
}

/**
* The root CSSList of a parsed file. Contains all top-level css contents, mostly declaration blocks, but also any @-rules encountered.
*/
class CSSDocument extends CSSList {
	/**
	* Gets all CSSDeclarationBlock objects recursively.
	*/
	public function getAllDeclarationBlocks() {
		$aResult = array();
		$this->allDeclarationBlocks($aResult);
		return $aResult;
	}

	/**
	* @deprecated use getAllDeclarationBlocks()
	*/
	public function getAllSelectors() {
		return $this->getAllDeclarationBlocks();
	}

    /**
     * Return a list of single class selectors
     *
     * @return array
     */
    public function getSingleClassSelectorList() {
        $results = array();
        $blocks = $this->getAllDeclarationBlocks();
        foreach($blocks as $block)
        {
            $selectors = $block->getSelectors();

            foreach($selectors as $sel)
            {
                $selctorParts = explode(" ", $sel);
                if(count($selctorParts) > 1)
                {
                    continue;
                }

                //Only accept class selectors
                if(preg_match('/^\./', $sel))
                {
                    $results[] = (string)$sel;
                }
            }
        }
        return $results;
    }


	/**
	* Returns all CSSRuleSet objects found recursively in the tree.
	*/
	public function getAllRuleSets() {
		$aResult = array();
		$this->allRuleSets($aResult);
		return $aResult;
	}
	
	/**
	* Returns all CSSValue objects found recursively in the tree.
	* @param (object|string) $mElement the CSSList or CSSRuleSet to start the search from (defaults to the whole document). If a string is given, it is used as rule name filter (@see{CSSRuleSet->getRules()}).
	* @param (bool) $bSearchInFunctionArguments whether to also return CSSValue objects used as CSSFunction arguments.
	*/
	public function getAllValues($mElement = null, $bSearchInFunctionArguments = false) {
		$sSearchString = null;
		if($mElement === null) {
			$mElement = $this;
		} else if(is_string($mElement)) {
			$sSearchString = $mElement;
			$mElement = $this;
		}
		$aResult = array();
		$this->allValues($mElement, $aResult, $sSearchString, $bSearchInFunctionArguments);
		return $aResult;
	}

	/**
	* Returns all CSSSelector objects found recursively in the tree.
	* Note that this does not yield the full CSSDeclarationBlock that the selector belongs to (and, currently, there is no way to get to that).
	* @param $sSpecificitySearch An optional filter by specificity. May contain a comparison operator and a number or just a number (defaults to "==").
	* @example getSelectorsBySpecificity('>= 100')
	*/
	public function getSelectorsBySpecificity($sSpecificitySearch = null) {
		if(is_numeric($sSpecificitySearch) || is_numeric($sSpecificitySearch[0])) {
			$sSpecificitySearch = "== $sSpecificitySearch";
		}
		$aResult = array();
		$this->allSelectors($aResult, $sSpecificitySearch);
		return $aResult;
	}
  
  /**
   * Expands all shorthand properties to their long value
   */ 
  public function expandShorthands()
  {
    foreach($this->getAllDeclarationBlocks() as $oDeclaration)
    {
      $oDeclaration->expandShorthands();
    }
  }

  /*
   * Create shorthands properties whenever possible
   */
  public function createShorthands()
  {
    foreach($this->getAllDeclarationBlocks() as $oDeclaration)
    {
      $oDeclaration->createShorthands();
    }
  }
}

/**
* A CSSList consisting of the CSSList and CSSList objects found in a @media query.
*/
class CSSMediaQuery extends CSSList {
	private $sQuery;
	
	public function __construct() {
		parent::__construct();
		$this->sQuery = null;
	}
	
	public function setQuery($sQuery) {
			$this->sQuery = $sQuery;
	}

	public function getQuery() {
			return $this->sQuery;
	}
	
	public function __toString() {
		$sResult = "@media {$this->sQuery} {";
		$sResult .= parent::__toString();
		$sResult .= '}';
		return $sResult;
	}
}
