<?php
/**
 * Verifies that all variables are declared in the proper scope.
 *
 * @package DWS
 * @subpackage Sniffs
 */

/**
 * Verifies that all variables are declared in the proper scope.
 *
 * @package DWS
 * @subpackage Sniffs
 */
final class DWS_Sniffs_Scope_VariableScopeSniff extends PHP_CodeSniffer_Standards_AbstractVariableSniff
{
    /**
     * All of the assignments made to all of the variables are included here.
     * 
     * @var array
     */
    private $_variableAsignments = array();

    /**
     * Processes normal variables.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file where this token was found.
     * @param int $stackPtr The position where the token was found.
     *
     * @return void
     */
    protected function processVariable(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        // FIXME: This needs to do some things differently.
        //
        // Most importantly, as it goes through the file it needs to determine whether the variable is being assigned or not.  A variable is
        // being assigned when it is in a function parameter or use list, when it is to the left of a T_EQUAL (but not other assignment tokens),
        // or when it is in the righthand part of a foreach statement.
        //
        // We also then need to determine when a variable is being read from.  This can be when it is in a condition statement of a control
        // structure (if statement, while statement, foreach statement, switch statement, second or third part of a for statement), when it is
        // to the right of an assignment operator, when it is a parameter to a function or php construct (return, echo, etc).  This needs to
        // then be modified such that if the variable is in a call to isset, unset, list, or empty it does not count as being read.  Also,
        // depending on the voting, multiple assignments in one statement may be allowed as long as there is nothing but direct assignments.
        //
        // So whenever we come across an assignment, we need to log what the nearest scope opener was for that assignment within absolute php
        // scope.  This needs to be cognizant of the global keyword, superglobals, $this, static variables, etc, to ensure that we do know what
        // the scope is (when global, we assume that it is being used correctly).  If we have a non-global scope, then we will be building a
        // list of all the places where that variable is assigned.
        //
        // Whenever we come across a read, we check each of the assignments to that variable within our current absolute php scope (assuming
        // correct usage for global), and look to see if that assignment was made inside of a scope that includes this read.  If none of the
        // assignments were made inside of a parent scope, then we can throw an error.
        //
        // Although the previous 2 paragraphs were written in this order for clarity, in actuality, we'd probably do the isRead() check before
        // the isAssignment() check so that we don't consider `if ($foo = 'bar')` valid.  This may need to be amended to account for multiple
        // assignments in a single line, in which case we would have special logic for handling that case.
        //
        // We're going to need some special logic for unset so that reads after an unset don't pass.

        $tokens = $phpcsFile->getTokens();
        echo "Line {$tokens[$stackPtr]['line']} {$tokens[$stackPtr]['content']} " . trim(var_export($this->_isAssignment($phpcsFile, $stackPtr), true)) . ' ' .  trim(var_export($this->_isRead($phpcsFile, $stackPtr), true)) . "\n";
        return;
        $variableName = $tokens[$stackPtr]['content'];
        $scopeIdentifier = $phpcsFile->getFilename() . $variableName;
        $level = $tokens[$stackPtr]['level'];
        $functionIndex = $phpcsFile->findPrevious(T_FUNCTION, $stackPtr);
        $lastScopeOpen = $phpcsFile->findPrevious(PHP_CodeSniffer_Tokens::$scopeOpeners, $stackPtr);

        //Inline scope openers do not increment the level value
        $scopeOpenDistance = $tokens[$stackPtr]['line'] - $tokens[$lastScopeOpen]['line'];
        if (
            in_array($tokens[$lastScopeOpen]['code'], PHP_CodeSniffer_Tokens::$scopeOpeners) === true
            && ($scopeOpenDistance === 1 || $scopeOpenDistance === 0)//Include the variables in the condition
            && $tokens[$stackPtr]['level'] === $tokens[$lastScopeOpen]['level']
        ) {
            ++$level;
        }

        if (
            $functionIndex !== false
            && array_key_exists('scope_closer', $tokens[$functionIndex])
            && $tokens[$functionIndex]['scope_closer'] > $stackPtr
        ) {
            //Member variables are always ok
            if ($variableName === '$this') {
                return;
            }

            // find previous non-whitespace token. if it's a double colon, assume static class var
            $objOperator = $phpcsFile->findPrevious(array(T_WHITESPACE), ($stackPtr - 1), null, true);
            if ($tokens[$objOperator]['code'] === T_DOUBLE_COLON) {
                return;
            }

            $scopeIdentifier .= $tokens[$functionIndex]['scope_condition'];
        }

        //If this is the first time we've seen this variable in this file/function store the scope depth.
        if (array_key_exists($scopeIdentifier, $this->_variableScopes) === false) {
            $this->_variableScopes[$scopeIdentifier] = $level;
        } elseif ($this->_variableScopes[$scopeIdentifier] > $level) {
            //Verify that the variables we've seen are not appearing in higher scopes.
            $phpcsFile->addWarning("Variable '{$variableName}' is in the wrong scope.", $stackPtr, 'Found');
        }
    }

    /**
     * Processes the function tokens within the class.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file where this token was found.
     * @param int $stackPtr The position where the token was found.
     *
     * @return void
     */
    protected function processMemberVar(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        //Do Nothing
    }

    /**
     * Processes variables in double quoted strings.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file where this token was found.
     * @param int $stackPtr The position where the token was found.
     *
     * @return void
     */
    protected function processVariableInString(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        //Do Nothing
    }

    private function _isAssignment(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $nextInStatement = $phpcsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, $stackPtr + 1, null, true, null, true);
        $nextIsEqual = $nextInStatement !== false && $tokens[$nextInStatement]['code'] === T_EQUAL;

        // If this is part of a variable variable, then it's not being written to.
        $prevDollar = $phpcsFile->findPrevious(array(T_DOLLAR), $stackPtr - 1, null, false, null, true);
        if ($prevDollar !== false) {
            // This is to handle weird stuff like ${$foo = 'bar'} = 'baz', so that $foo does register as an assignment.
            $nextAfterDollar = $phpcsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, $prevDollar + 1, null, true);
            if ($tokens[$nextAfterDollar]['code'] === T_OPEN_CURLY_BRACKET && $nextIsEqual) {
                return true;
            }

            return false;
        }

        // If the next token is an assignment token then this is an assignment.
        if ($nextIsEqual) {
            return true;
        }

        // If we are inside of a function or closure's parameter  or use list, or we are in a list() construct, then this is an assignment.
        $prevScopeStatement = $phpcsFile->findPrevious(array(T_LIST, T_FUNCTION, T_USE, T_CLOSURE), $stackPtr - 1, null, false, null, true);
        if ($prevScopeStatement !== false) {
            // T_LIST and T_USE do not have parenthesis_opener/closer set on the tokens themselves, so we need to hunt for the next parenthesis
            // and use it as the base of the check.
            if (!array_key_exists('parenthesis_closer', $tokens[$prevScopeStatement])) {
                $openingParenthesis = $phpcsFile->findNext(array(T_OPEN_PARENTHESIS), $prevScopeStatement + 1, null, false, null, true);
                if ($openingParenthesis !== false) {
                    $prevScopeStatement = $openingParenthesis;
                }
            }

            if (
                array_key_exists('parenthesis_closer', $tokens[$prevScopeStatement]) &&
                $stackPtr < $tokens[$prevScopeStatement]['parenthesis_closer']
            ) {
                return true;
            }
        }

        // If we are in a global statement, then this is an assignment.
        $prevGlobalInStatement = $phpcsFile->findPrevious(array(T_GLOBAL), $stackPtr - 1, null, false, null, true);
        if ($prevGlobalInStatement !== false) {
            return true;
        }

        // If we are after the as in a foreach statement, then this is an assignment.  This requires a bit of an extra check because the
        // findPrevious within the local statement stretches into the body of the foreach.  Not sure why this is the case.
        $prevAsInStatement = $phpcsFile->findPrevious(array(T_AS), $stackPtr - 1, null, false, null, true);
        if ($prevAsInStatement !== false) {
            $prevForeachInStatement = $phpcsFile->findPrevious(array(T_FOREACH), $prevAsInStatement - 1, null, false, null, true);
            if (
                $prevForeachInStatement !== false &&
                array_key_exists('parenthesis_closer', $tokens[$prevForeachInStatement]) &&
                $stackPtr < $tokens[$prevForeachInStatement]['parenthesis_closer']
            ) {
                return true;
            }
        }

        return false;
    }

    private function _isRead(PHP_CodeSniffer_File $phpcsFile, $stackPtr) {
        // If we are on the RHS of an assignment, this is a read.
        // FIXME: This is really not working well.  Assignments on previous lines seem to be getting counted into this just because they don't
        // have a semicolon separating them, which many php statements don't end in semicolons (function declarations for instance).
        if ($phpcsFile->findPrevious(PHP_CodeSniffer_Tokens::$assignmentTokens, $stackPtr - 1, null, false, null, true) !== false) {
            return true;
        }

        return false;
    }
}
