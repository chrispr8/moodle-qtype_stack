<?php
// This file is part of Stack - http://stack.maths.ed.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();


require_once(__DIR__ . '/casstring.units.class.php');

// CAS identifier related security data-lookups.
//
// @copyright  2018 Aalto University.
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
class stack_cas_security {
    // This holds a copy of the security-map.json so that it does not need to
    // be loaded too many times.
    private static $securitymap = null;

    /** @var bool if this security context considers units as constants. */
    private $units = false;

    /** @var string specific allowed words or groups of them. Used to expand
             the student allowed identifiers set. */
    private $allowedwords = '';
    private $allowedwordsasmap = null;

    /** @var string specific forbidden words or groups of them. Used to cut down
             the student allowed identifiers set. */
    private $forbiddenwords = '';
    private $forbiddenwordsasmap = null;

    /** @var array typically the teacher side variable identifiers. Used to cut
             down the student allowed identifiers set. */
    private $forbiddenkeys = array();



    /**
     * These lists are used by question authors for groups of words.
     * They should be lower case, because Maxima is lower case, and these correspond to Maxima names.
     * Actually, not lower case, Maxima is not case insensitive just check "ModeMatrix" for an example.
     */
    public static $keywordlists = array(
            '[[basic-algebra]]' => array('coeff' => true, 'conjugate' => true, 'cspline' => true, 'disjoin' => true, 'divisors' => true,
                    'ev' => true, 'eliminate' => true, 'equiv_classes' => true, 'expand' => true, 'expandwrt' => true, 'facsum' => true, 'factor' => true, 'find_root' => true,
                    'fullratsimp' => true, 'gcd' => true, 'gfactor' => true, 'imagpart' => true, 'intersection' => true, 'lcm' => true, 'logcontract' => true, 'logexpand' => true,
                    'member' => true, 'nroots' => true, 'nthroot' => true, 'numer' => true, 'partfrac' => true, 'polarform' => true, 'polartorect' => true, 'ratexpand' => true,
                    'ratsimp' => true, 'realpart' => true, 'round' => true, 'radcan' => true, 'num' => true, 'denom' => true, 'trigsimp' => true, 'trigreduce' => true, 'solve' => true,
                    'allroots' => true, 'simp' => true, 'setdifference' => true, 'sort' => true, 'subst' => true, 'trigexpand' => true, 'trigexpandplus' => true,
                    'trigexpandtimes' => true, 'triginverses' => true, 'trigrat' => true, 'trigreduce' => true, 'trigsign' => true, 'trigsimp' => true,
                    'truncate' => true, 'decimalplaces' => true, 'simplify' => true),
            '[[basic-calculus]]' => array('defint' => true, 'diff' => true, 'int' => true, 'integrate' => true, 'limit' => true, 'partial' => true, 'desolve' => true,
                    'express' => true, 'taylor' => true),
            '[[basic-matrix]]' => array('addmatrices' => true, 'adjoin' => true, 'augcoefmatrix' => true, 'blockmatrixp' => true, 'charpoly' => true,
                    'coefmatrix' => true, 'col' => true, 'columnop' => true, 'columnspace' => true, 'columnswap' => true, 'covect' => true, 'ctranspose' => true,
                    'determinant' => true, ' diag_matrix' => true, 'diagmatrix' => true, 'dotproduct' => true, 'echelon' => true, 'eigenvalues' => true,
                    'eigenvectors' => true, 'eivals' => true, 'eivects' => true, 'ematrix' => true, 'invert' => true, 'matrix_element_add' => true,
                    'matrix_element_mult' => true, 'matrix_element_transpose' => true, 'nullspace' => true, 'resultant' => true,
                    'rowop' => true, 'rowswap' => true, 'transpose' => true)
    );

    public static function is_good_function(string $identifier): bool {
        // Generic tool for telling if a given identifier matches a function.
        if (stack_cas_security::$securitymap === null) {
            // Initialise the map.
            $data = file_get_contents(__DIR__ . '/security-map.json');
            stack_cas_security::$securitymap = json_decode($data, true);
        }

        if (isset(stack_cas_security::$securitymap[$identifier])) {
            if (isset(stack_cas_security::$securitymap[$identifier]['function'])) {
                return stack_cas_security::$securitymap[$identifier]['function'] == 's';
            }
        }

        return false;
    }

    public function __construct($units = false, $allowedwords = '', $forbiddenwords = '', $forbiddenkeys = array()) {
        if (stack_cas_security::$securitymap === null) {
            // Initialise the map.
            $data = file_get_contents(__DIR__ . '/security-map.json');
            stack_cas_security::$securitymap = json_decode($data, true);
        }

        $this->units          = $units;
        $this->allowedwords   = $allowedwords;
        $this->forbiddenwords = $forbiddenwords;
        $this->forbiddenkeys  = $forbiddenkeys;

        if (!is_bool($this->units)) {
            throw new stack_exception('stack_cas_security: units must be a bool.');
        }
        if (!is_string($this->allowedwords)) {
            throw new stack_exception('stack_cas_security: allowedwords must be a string.');
        }
        if (!is_string($this->forbiddenwords)) {
            throw new stack_exception('stack_cas_security: forbiddenwords must be a string.');
        }
        if ($forbiddenkeys === null) {
            // TODO: Quite common issue in tests...
            // There are functions in the chain that want arrays but do not check.
            $this->forbiddenkeys  = array();
        }
        if (!is_array($this->forbiddenkeys)) {
            throw new stack_exception('stack_cas_security: forbiddenkeys must be an array.');
        }

        // Check that the keys if present are the correct way around.
        if (array_key_exists(0, $this->forbiddenkeys)) {
            $this->forbiddenkeys = array_flip($this->forbiddenkeys);
        }
    }

    public function set_allowedwords(string $allowedwords) {
        $this->allowedwords = $allowedwords;
        $this->allowedwordsasmap = null;
    }

    public function set_forbiddenwords(string $forbiddenwords) {
        $this->forbiddenwords = $forbiddenwords;
        $this->forbiddenwordsasmap = null;
    }

    public function set_forbiddenkeys(array $forbiddenkeys) {
        $this->forbiddenkeys  = $forbiddenkeys;
        // Check that the keys if present are the correct way around.
        if (array_key_exists(0, $this->forbiddenkeys)) {
            $this->forbiddenkeys = array_flip($this->forbiddenkeys);
        }

        // Check for keyword-lists. Although they should not exists here as this
        // is used to check for teacher reserved words. But they do exist in
        // tests.
        $real = array();
        foreach ($this->forbiddenkeys as $key => $duh) {
            if (isset(stack_cas_security::$keywordlists[strtolower($key)])) {
                foreach (stack_cas_security::$keywordlists[strtolower($key)] as $k => $v) {
                    $real[$k] = $v;
                }
            } else if (core_text::strlen($key) > 1){
                // As lenght 1 identifiers are always ok we skip them here.
                $real[$key] = true;
            }
        }
        $this->forbiddenkeys = $real;
    }

    public function set_units(bool $units) {
        $this->units = $units;
    }

    /**
     * Answers the question whether something can be called as a function.
     * Within the defined security scope.
     */
    public function is_allowed_to_call(string $security, string $identifier): bool {
        $foundsecurity = '?';
        if (isset(stack_cas_security::$securitymap[$identifier])) {
            if (isset(stack_cas_security::$securitymap[$identifier]['function'])) {
                $foundsecurity = stack_cas_security::$securitymap[$identifier]['function'];
            }
        }
        // Never, if it is forbidden.
        if ($foundsecurity === 'f') {
            return false;
        }

        // Check for forbidden words.
        if ($this->forbiddenwordsasmap == null) {
            $this->forbiddenwordsasmap = stack_cas_security::list_to_map($this->forbiddenwords);
        }
        if (isset($this->forbiddenwordsasmap[$identifier])) {
            // Forbidden words are not considered as typed. For now.
            return false;
        }

        // For backwards compatibility check for substrings.
        foreach ($this->forbiddenwordsasmap as $key => $duh) {
            if (strpos($identifier, $key) !== false) {
                // The 'i' in 'sin' case.
                return false;
            }
        }

        // If its already security 's' then all fine.
        if ($foundsecurity === 's') {
            return true;
        }

        // Try promoting to security 's'.
        if ($this->allowedwordsasmap == null) {
            $this->allowedwordsasmap = stack_cas_security::list_to_map($this->allowedwords);
        }
        if (isset($this->allowedwordsasmap[$identifier])) {
            // Allow words might be typed.
            if (is_array($this->allowedwordsasmap[$identifier])) {
                return isset($this->allowedwordsasmap[$identifier]['function']);
            } else {
                return true;
            }
        }

        // Keys are the names used by author and we need to allow them to be bypassed.
        if (isset($this->forbiddenkeys[$identifier])) {
            return false;
        }

        // Special case. Very special indeed.
        if ($security === 's' && $identifier === 'In') {
            return false;
        }

        // If the identifer is less than three char then students have permissions.
        if ($security === 's' && core_text::strlen($identifier) <= 2) {
            return true;
        }

        // If no matches at all then allowed for security='t'.
        return $security === 't';
    }

    /**
     * Answers the question whether something can be referenced as a variable.
     * Within the defined security scope.
     */
    public function is_allowed_to_read(string $security, string $identifier): bool {
        $foundsecurity = '?';
        if (isset(stack_cas_security::$securitymap[$identifier])) {
            if (isset(stack_cas_security::$securitymap[$identifier]['variable'])) {
                $foundsecurity = stack_cas_security::$securitymap[$identifier]['variable'];
            }
            if (isset(stack_cas_security::$securitymap[$identifier]['constant'])) {
                $foundsecurity = stack_cas_security::$securitymap[$identifier]['constant'];
            }
        }
        // Never, if it is forbidden.
        if ($foundsecurity === 'f') {
            return false;
        }

        // Forbid keywords and operators as variable names.
        if ($this->has_feature($identifier, 'keyword') || $this->has_feature($identifier, 'operator')) {
            return false;
        }

        // Check for forbidden words.
        if ($this->forbiddenwordsasmap == null) {
            $this->forbiddenwordsasmap = stack_cas_security::list_to_map($this->forbiddenwords);
        }
        if (isset($this->forbiddenwordsasmap[$identifier])) {
            // Forbidden words are not considered as typed. For now.
            return false;
        }

        // For backwards compatibility check for substrings.
        foreach ($this->forbiddenwordsasmap as $key => $duh) {
            if (strpos($identifier, $key) !== false) {
                // The 'i' in 'sin' case.
                return false;
            }
        }

        // Units.
        if ($this->units) {
            $units = stack_cas_casstring_units::get_permitted_units(0);
            if (isset($units[$identifier])) {
                return true;
            }
        }


        // If its already security 's' then all fine.
        if ($foundsecurity === 's') {
            return true;
        }

        // Try promoting to security 's'.
        if ($this->allowedwordsasmap == null) {
            $this->allowedwordsasmap = stack_cas_security::list_to_map($this->allowedwords);
        }
        if (isset($this->allowedwordsasmap[$identifier])) {
            // Allow words might be typed.
            if (is_array($this->allowedwordsasmap[$identifier])) {
                return isset($this->allowedwordsasmap[$identifier]['variable']) || isset($this->allowedwordsasmap[$identifier]['constant']);
            } else {
                return true;
            }
        }

        // Forbidden author used ones unless allowed above.
        if (isset($this->forbiddenkeys[$identifier])) {
            return false;
        }

        // If the identifer is less than three char then students have permissions.
        if ($security === 's' && core_text::strlen($identifier) <= 2) {
            return true;
        }

        // If no matches at all then allowed for security='t'.
        return $security === 't';
    }

    /**
     * Answers the question whether something can be written as a variable.
     * Within the defined security scope.
     */
    public function is_allowed_to_write(string $security, string $identifier): bool {
        // If not readable then not writable either and constants are a thing.
        if ($this->has_feature($identifier, 'constant') ||
            !$this->is_allowed_to_read($security, $identifier)) {
            return false;
        }

        return true;
    }

    /**
     * Answers the question whether something is an allowed operator.
     * Within the defined security scope.
     */
    public function is_allowed_as_operator(string $security, string $identifier): bool {
        if ($this->has_feature($identifier, 'constant')) {
            return false;
        }

        $foundsecurity = '?';
        if (isset(stack_cas_security::$securitymap[$identifier])) {
            if (isset(stack_cas_security::$securitymap[$identifier]['operator'])) {
                $foundsecurity = stack_cas_security::$securitymap[$identifier]['operator'];
            } else {
                // In the case of operators they must be defined as operators in the map.
                return false;
            }
        } else {
            return false;
        }
        // Never, if it is forbidden.
        if ($foundsecurity === 'f') {
            return false;
        }

        // Check for forbidden words.
        if ($this->forbiddenwordsasmap == null) {
            $this->forbiddenwordsasmap = stack_cas_security::list_to_map($this->forbiddenwords);
        }
        if (isset($this->forbiddenwordsasmap[$identifier])) {
            // Forbidden words are not considered as typed. For now.
            return false;
        }

        // If its already security 's' then all fine.
        if ($foundsecurity === 's') {
            return true;
        }

        // Try promoting to security 's'.
        if ($this->allowedwordsasmap == null) {
            $this->allowedwordsasmap = stack_cas_security::list_to_map($this->allowedwords);
        }
        if (isset($this->allowedwordsasmap[$identifier])) {
            // Allow words might be typed.
            if (is_array($this->allowedwordsasmap[$identifier])) {
                return isset($this->allowedwordsasmap[$identifier]['operator']);
            } else {
                return true;
            }
        }

        // If no matches at all then allowed for security='t'.
        return $security === 't';
    }


    /**
     * Checks the features of an identifer. Special dealing with 'constant'.
     * Typically used to identify constants and mapfunctions.
     *
     * Note that by feature we mean anything that may have a value. For
     * example 'function' is a feature but its value is the thing that matters
     * when deciding whether it is ok to call.
     */
    public function has_feature(string $identifier, string $feature): bool {
        if ($feature === 'constant' && $this->units) {
            $units = stack_cas_casstring_units::get_permitted_units(0);
            if (isset($units[$identifier])) {
                // In units mode unit identifiers are constants.
                return true;
            }
        }
        if (isset(stack_cas_security::$securitymap[$identifier])) {
            return isset(stack_cas_security::$securitymap[$identifier][$feature]);
        }
        // If not part of the map then it has no features.
        return false;
    }

    /**
     * Checks for case variant keys of the given identifier.
     * Returns all keys that we know of that are not matching.
     */
    public function get_case_variants(string $identifier): array {
        // TODO: should this be typed? i.e. return only function or variable
        // identifiers on demand? And should it drop forbidden items?
        $r = array();
        $l = strtolower($identifier);
        foreach (stack_cas_security::$securitymap as $key => $duh) {
            if ($identifier !== $key && strtolower($key) === $l) {
                $r[] = $key;
            }
        }
        foreach (stack_cas_security::list_to_map($this->allowedwords) as $key => $duh) {
            if ($identifier !== $key && strtolower($key) === $l) {
                $r[] = $key;
            }
        }

        if ($this->units) {
            // This has a separate implementation in caastring_units but Lets
            // do things just a bit differently.
            $units = stack_cas_casstring_units::get_permitted_units(core_text::strlen($identifier));
            foreach ($units as $key) {
                if ($identifier !== $key && strtolower($key) === $l) {
                    $r[] = $key;
                }
            }
        }

        sort($r);

        return $r;
    }


    // Takes a string form allowed/forbiddenwords list and turns it into an array.
    public static function list_to_map(string $list): array {
        // Probably called often, why waste time repeating the loops.
        static $cache = array();
        if (isset($cache[$list])) {
            return $cache[$list];
        }
        $result = array();

        $list = str_replace('\,', 'COMMA_TAG', $list);


        foreach (explode(',', $list) as $item) {
            $item = trim($item);
            if ($item !== '') {
                // If its a name of a list.
                if (isset(stack_cas_security::$keywordlists[$item])) {
                    $result = array_merge($result, stack_cas_security::$keywordlists[$item]);
                } else if (isset(stack_cas_security::$keywordlists[strtolower($item)])) {
                    // These are present in upper case in old test cases.
                    $result = array_merge($result, stack_cas_security::$keywordlists[strtolower($item)]);
                } else {
                    if ($item === 'COMMA_TAG') {
                        $result[','] = true;
                        // We might want to handle something like '"\\,","foo"'
                        // at some point the future...
                    } else {
                        $result[$item] = true;
                    }
                }
            }
        }

        $cache[$list] = $result;
        return $result;
    }

}
