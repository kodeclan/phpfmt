<?php
# Copyright (c) 2015, phpfmt and its authors
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
#
# 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

//FormatterPass holds all data structures necessary to traverse a stream of
//tokens, following the concept of bottom-up it works as a platform on which
//other passes can be built on.
abstract class FormatterPass {
	protected $cache = [];

	protected $code = '';

	// stream of tokens
	protected $ignoreFutileTokens = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

	protected $indent = 0;

	protected $indentChar = "\t";

	protected $newLine = "\n";

	// holds the final outputed code
	protected $ptr = 0;

	protected $tkns = [];

	protected $useCache = false;

	private $memo = [null, null];

	private $memoUseful = [null, null];

	abstract public function candidate(string $source, array $foundTokens): bool;

	abstract public function format(string $source): string;

	protected function alignPlaceholders(string $origPlaceholder, int $contextCounter) {
		for ($j = 0; $j <= $contextCounter; ++$j) {
			$placeholder = sprintf($origPlaceholder, $j);
			if (false === strpos($this->code, $placeholder)) {
				continue;
			}
			if (1 === substr_count($this->code, $placeholder)) {
				$this->code = str_replace($placeholder, '', $this->code);
				continue;
			}
			$lines = explode($this->newLine, $this->code);
			$linesWithPlaceholder = [];
			$blockCount = 0;

			foreach ($lines as $idx => $line) {
				if (false !== strpos($line, $placeholder)) {
					$linesWithPlaceholder[$blockCount][] = $idx;
					continue;
				}
				++$blockCount;
				$linesWithPlaceholder[$blockCount] = [];
			}

			$i = 0;
			foreach ($linesWithPlaceholder as $group) {
				++$i;
				$farthest = 0;
				foreach ($group as $idx) {
					$farthest = max($farthest, strpos($lines[$idx], $placeholder));
				}
				foreach ($group as $idx) {
					$line = $lines[$idx];
					$current = strpos($line, $placeholder);
					$delta = abs($farthest - $current);
					if ($delta > 0) {
						$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
						$lines[$idx] = $line;
					}
				}
			}
			$this->code = str_replace($placeholder, '', implode($this->newLine, $lines));
		}
	}

	protected function appendCode(string $code) {
		$this->code .= $code;
	}

	protected function getCrlf(): string {
		return $this->newLine;
	}

	protected function getCrlfIndent(): string {
		return $this->getCrlf() . $this->getIndent();
	}

	protected function getIndent(int $increment = 0): string {
		return str_repeat($this->indentChar, $this->indent + $increment);
	}

	protected function getSpace(bool $true = true): string {
		return $true ? ' ' : '';
	}

	protected function getToken($token): array{
		$ret = [$token, $token];
		if (isset($token[1])) {
			$ret = $token;
		}
		return $ret;
	}

	protected function hasLn(string $text): bool {
		return (false !== strpos($text, $this->newLine));
	}

	protected function hasLnAfter(): bool{
		$id = null;
		$text = null;
		list($id, $text) = $this->inspectToken();
		return T_WHITESPACE === $id && $this->hasLn($text);
	}

	protected function hasLnBefore(): bool{
		$id = null;
		$text = null;
		list($id, $text) = $this->inspectToken(-1);
		return T_WHITESPACE === $id && $this->hasLn($text);
	}

	protected function hasLnLeftToken(): bool {
		list(, $text) = $this->getToken($this->leftToken());
		return $this->hasLn($text);
	}

	protected function hasLnRightToken(): bool {
		list(, $text) = $this->getToken($this->rightToken());
		return $this->hasLn($text);
	}

	protected function inspectToken(int $delta = 1): array{
		if (!isset($this->tkns[$this->ptr + $delta])) {
			return [null, null];
		}
		return $this->getToken($this->tkns[$this->ptr + $delta]);
	}

	protected function isShortArray(): bool {
		return !$this->leftTokenIs([
			ST_BRACKET_CLOSE,
			ST_CURLY_CLOSE,
			ST_PARENTHESES_CLOSE,
			ST_QUOTE,
			T_CONSTANT_ENCAPSED_STRING,
			T_STRING,
			T_VARIABLE,
		]);
	}

	protected function leftMemoTokenIs($token): bool {
		return $this->resolveFoundToken($this->memo[0], $token);
	}

	protected function leftMemoUsefulTokenIs($token, bool $debug = false): bool {
		return $this->resolveFoundToken($this->memoUseful[0], $token);
	}

	protected function leftToken(array $ignoreList = []) {
		$i = $this->leftTokenIdx($ignoreList);

		return $this->tkns[$i];
	}

	protected function leftTokenIdx(array $ignoreList = []): int{
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$i = $this->walkLeft($this->tkns, $this->ptr, $ignoreList);

		return $i;
	}

	protected function leftTokenIs($token, array $ignoreList = []): bool {
		return $this->tokenIs('left', $token, $ignoreList);
	}

	protected function leftTokenSubsetAtIdx(array $tkns, int $idx, array $ignoreList = []): int{
		$ignoreList = $this->resolveIgnoreList($ignoreList);
		$idx = $this->walkLeft($tkns, $idx, $ignoreList);

		return $idx;
	}

	protected function leftTokenSubsetIsAtIdx(array $tkns, int $idx, $token, array $ignoreList = []): bool{
		$idx = $this->leftTokenSubsetAtIdx($tkns, $idx, $ignoreList);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function leftUsefulToken() {
		return $this->leftToken($this->ignoreFutileTokens);
	}

	protected function leftUsefulTokenIdx(): int {
		return $this->leftTokenIdx($this->ignoreFutileTokens);
	}

	protected function leftUsefulTokenIs($token): bool {
		return $this->leftTokenIs($token, $this->ignoreFutileTokens);
	}

	protected function memoPtr() {
		$t = $this->tkns[$this->ptr][0];

		if (T_WHITESPACE !== $t) {
			$this->memo[0] = $this->memo[1];
			$this->memo[1] = $t;
		}

		if (T_WHITESPACE !== $t && T_COMMENT !== $t && T_DOC_COMMENT !== $t) {
			$this->memoUseful[0] = $this->memoUseful[1];
			$this->memoUseful[1] = $t;
		}
	}

	protected function peekAndCountUntilAny(array $tkns, int $ptr, array $tknids): array{
		$tknids = array_flip($tknids);
		$tknsSize = sizeof($tkns);
		$countTokens = [];
		$id = null;
		for ($i = $ptr; $i < $tknsSize; ++$i) {
			$token = $tkns[$i];
			list($id) = $this->getToken($token);
			if (T_WHITESPACE == $id || T_COMMENT == $id || T_DOC_COMMENT == $id) {
				continue;
			}
			if (!isset($countTokens[$id])) {
				$countTokens[$id] = 0;
			}
			++$countTokens[$id];
			if (isset($tknids[$id])) {
				break;
			}
		}
		return [$id, $countTokens];
	}

	protected function printAndStopAt($tknids): array{
		if (is_scalar($tknids)) {
			$tknids = [$tknids];
		}
		$tknids = array_flip($tknids);
		$touchedLn = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			if (!$touchedLn && T_WHITESPACE == $id && $this->hasLn($text)) {
				$touchedLn = true;
			}
			if (isset($tknids[$id])) {
				return [$id, $text, $touchedLn];
			}
			$this->appendCode($text);
		}
		return [null, null];
	}

	protected function printAndStopAtEndOfParamBlock() {
		$count = 1;
		$paramCount = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];

			if (ST_COMMA == $id && 1 == $count) {
				++$paramCount;
			}
			if (ST_BRACKET_OPEN == $id) {
				$this->appendCode($text);
				$this->printBlock(ST_BRACKET_OPEN, ST_BRACKET_CLOSE);
				continue;
			}
			if (ST_CURLY_OPEN == $id || T_CURLY_OPEN == $id || T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				$this->appendCode($text);
				$this->printCurlyBlock();
				continue;
			}
			if (ST_PARENTHESES_OPEN == $id) {
				++$count;
			}
			if (ST_PARENTHESES_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				prev($this->tkns);
				break;
			}
			$this->appendCode($text);
		}
		return $paramCount;
	}

	protected function printBlock(string $start, string $end) {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);

			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function printCurlyBlock() {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);

			if (ST_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				++$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function printUntil($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);
			if ($tknid == $id) {
				break;
			}
		}
	}

	protected function printUntilAny(array $tknids) {
		$tknids = array_flip($tknids);
		$whitespaceNewLine = false;
		$id = null;
		if (isset($tknids[$this->newLine])) {
			$whitespaceNewLine = true;
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);
			if ($whitespaceNewLine && T_WHITESPACE == $id && $this->hasLn($text)) {
				break;
			}
			if (isset($tknids[$id])) {
				break;
			}
		}
		return $id;
	}

	protected function printUntilTheEndOfString() {
		$this->printUntil(ST_QUOTE);
	}

	protected function refInsert(array &$tkns, int &$ptr, array $item) {
		array_splice($tkns, $ptr, 0, [$item]);
		++$ptr;
	}

	protected function refSkipBlocks(array $tkns, int &$ptr) {
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];

			if (T_CLOSE_TAG == $id) {
				return;
			}

			if (T_DO == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				continue;
			}

			if (T_WHILE == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				if ($this->rightTokenSubsetIsAtIdx(
					$tkns,
					$ptr,
					ST_CURLY_OPEN,
					$this->ignoreFutileTokens
				)) {
					$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
					$this->refWalkCurlyBlock($tkns, $ptr);
					return;
				}
			}

			if (T_FOR == $id || T_FOREACH == $id || T_SWITCH == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				return;
			}

			if (T_TRY == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				while (
					$this->rightTokenSubsetIsAtIdx(
						$tkns,
						$ptr,
						T_CATCH,
						$this->ignoreFutileTokens
					)
				) {
					$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
					$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
					$this->refWalkCurlyBlock($tkns, $ptr);
				}
				if ($this->rightTokenSubsetIsAtIdx(
					$tkns,
					$ptr,
					T_FINALLY,
					$this->ignoreFutileTokens
				)) {
					$this->refWalkUsefulUntil($tkns, $ptr, T_FINALLY);
					$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
					$this->refWalkCurlyBlock($tkns, $ptr);
				}
				return;
			}

			if (T_IF == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				while (true) {
					if (
						$this->rightTokenSubsetIsAtIdx(
							$tkns,
							$ptr,
							T_ELSEIF,
							$this->ignoreFutileTokens
						)
					) {
						$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
						$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
						$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
						$this->refWalkCurlyBlock($tkns, $ptr);
						continue;
					} elseif (
						$this->rightTokenSubsetIsAtIdx(
							$tkns,
							$ptr,
							T_ELSE,
							$this->ignoreFutileTokens
						)
					) {
						$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
						$this->refWalkCurlyBlock($tkns, $ptr);
						break;
					}
					break;
				}
				return;
			}

			if (
				ST_CURLY_OPEN == $id ||
				T_CURLY_OPEN == $id ||
				T_DOLLAR_OPEN_CURLY_BRACES == $id
			) {
				$this->refWalkCurlyBlock($tkns, $ptr);
				continue;
			}

			if (ST_PARENTHESES_OPEN == $id) {
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				continue;
			}

			if (ST_BRACKET_OPEN == $id) {
				$this->refWalkBlock($tkns, $ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE);
				continue;
			}

			if (ST_SEMI_COLON == $id) {
				return;
			}
		}
		--$ptr;
	}

	protected function refSkipIfTokenIsAny(array $tkns, int &$ptr, array $skipIds) {
		$skipIds = array_flip($skipIds);
		++$ptr;
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];
			if (!isset($skipIds[$id])) {
				break;
			}
		}
	}

	protected function refWalkBackUsefulUntil(array $tkns, int &$ptr, array $expectedId) {
		$expectedId = array_flip($expectedId);
		do {
			$ptr = $this->walkLeft($tkns, $ptr, $this->ignoreFutileTokens);
		} while (isset($expectedId[$tkns[$ptr][0]]));
	}

	protected function refWalkBlock(array $tkns, int &$ptr, string $start, string $end) {
		$count = 0;
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];
			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function refWalkBlockReverse(array $tkns, int &$ptr, string $start, string $end) {
		$count = 0;
		for (; $ptr >= 0; --$ptr) {
			$id = $tkns[$ptr][0];
			if ($start == $id) {
				--$count;
			}
			if ($end == $id) {
				++$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function refWalkCurlyBlock(array $tkns, int &$ptr) {
		$count = 0;
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];
			if (ST_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				++$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function refWalkCurlyBlockReverse(array $tkns, int &$ptr) {
		$count = 0;
		for (; $ptr >= 0; --$ptr) {
			$id = $tkns[$ptr][0];
			if (ST_CURLY_OPEN == $id) {
				--$count;
			}
			if (T_CURLY_OPEN == $id) {
				--$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				--$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				++$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function refWalkUsefulUntil(array $tkns, int &$ptr, $expectedId) {
		do {
			$ptr = $this->walkRight($tkns, $ptr, $this->ignoreFutileTokens);
		} while ($expectedId != $tkns[$ptr][0]);
	}

	protected function refWalkUsefulUntilReverse(array $tkns, int &$ptr, $expectedId) {
		do {
			$ptr = $this->walkLeft($tkns, $ptr, $this->ignoreFutileTokens);
		} while ($ptr >= 0 && $expectedId != $tkns[$ptr][0]);
	}

	protected function render(array $tkns = null): string {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}

		$tkns = array_filter($tkns);
		$str = '';
		foreach ($tkns as $token) {
			list(, $text) = $this->getToken($token);
			$str .= $text;
		}
		return $str;
	}

	protected function renderLight(array $tkns = null): string {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}
		$str = '';
		foreach ($tkns as $token) {
			$str .= $token[1];
		}
		return $str;
	}

	protected function rightToken(array $ignoreList = []) {
		$i = $this->rightTokenIdx($ignoreList);

		return $this->tkns[$i];
	}

	protected function rightTokenIdx(array $ignoreList = []): int{
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$i = $this->walkRight($this->tkns, $this->ptr, $ignoreList);

		return $i;
	}

	protected function rightTokenIs($token, array $ignoreList = []): bool {
		return $this->tokenIs('right', $token, $ignoreList);
	}

	protected function rightTokenSubsetAtIdx(array $tkns, int $idx, array $ignoreList = []): int{
		$ignoreList = $this->resolveIgnoreList($ignoreList);
		$idx = $this->walkRight($tkns, $idx, $ignoreList);

		return $idx;
	}

	protected function rightTokenSubsetIsAtIdx(array $tkns, int $idx, $token, array $ignoreList = []): bool{
		$idx = $this->rightTokenSubsetAtIdx($tkns, $idx, $ignoreList);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function rightUsefulToken() {
		return $this->rightToken($this->ignoreFutileTokens);
	}

	protected function rightUsefulTokenIdx(): int {
		return $this->rightTokenIdx($this->ignoreFutileTokens);
	}

	protected function rightUsefulTokenIs($token): bool {
		return $this->rightTokenIs($token, $this->ignoreFutileTokens);
	}

	protected function rtrimAndAppendCode(string $code) {
		$this->code = rtrim($this->code) . $code;
	}

	protected function rtrimLnAndAppendCode(string $code) {
		$this->code = rtrim($this->code, "\t ") . $code;
	}

	protected function scanAndReplace(array &$tkns, int &$ptr, string $start, string $end, string $call, array $lookFor): string{
		$lookFor = array_flip($lookFor);
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = '';
		$tknCount = 1;
		$foundPotentialTokens = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			if (isset($lookFor[$id])) {
				$foundPotentialTokens = true;
			}
			if ($start == $id) {
				++$tknCount;
			}
			if ($end == $id) {
				--$tknCount;
			}
			$tkns[$ptr] = null;
			if (0 == $tknCount) {
				break;
			}
			$tmp .= $text;
		}
		if ($foundPotentialTokens) {
			return $start . str_replace($placeholder, '', $this->{$call}($placeholder . $tmp)) . $end;
		}
		return $start . $tmp . $end;
	}

	protected function scanAndReplaceCurly(array &$tkns, int &$ptr, string $start, string $call, array $lookFor): string{
		$lookFor = array_flip($lookFor);
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = '';
		$tknCount = 1;
		$foundPotentialTokens = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			if (isset($lookFor[$id])) {
				$foundPotentialTokens = true;
			}
			if (ST_CURLY_OPEN == $id) {
				if (empty($start)) {
					$start = ST_CURLY_OPEN;
				}
				++$tknCount;
			}
			if (T_CURLY_OPEN == $id) {
				if (empty($start)) {
					$start = ST_CURLY_OPEN;
				}
				++$tknCount;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				if (empty($start)) {
					$start = ST_DOLLAR . ST_CURLY_OPEN;
				}
				++$tknCount;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$tknCount;
			}
			$tkns[$ptr] = null;
			if (0 == $tknCount) {
				break;
			}
			$tmp .= $text;
		}
		if ($foundPotentialTokens) {
			return $start . str_replace($placeholder, '', $this->{$call}($placeholder . $tmp)) . ST_CURLY_CLOSE;
		}
		return $start . $tmp . ST_CURLY_CLOSE;
	}

	protected function setIndent(int $increment) {
		$this->indent += $increment;
		if ($this->indent < 0) {
			$this->indent = 0;
		}
	}

	protected function siblings(array $tkns, int $ptr): array{
		$ignoreList = $this->resolveIgnoreList([T_WHITESPACE]);
		$left = $this->walkLeft($tkns, $ptr, $ignoreList);
		$right = $this->walkRight($tkns, $ptr, $ignoreList);
		return [$left, $right];
	}

	protected function substrCountTrailing(string $haystack, string $needle): int {
		return strlen(rtrim($haystack, " \t")) - strlen(rtrim($haystack, " \t" . $needle));
	}

	protected function tokenIs(string $direction, $token, array $ignoreList = []): bool {
		if ('left' != $direction) {
			$direction = 'right';
		}
		if (!$this->useCache) {
			return $this->{$direction . 'tokenSubsetIsAtIdx'}($this->tkns, $this->ptr, $token, $ignoreList);
		}

		$key = $this->calculateCacheKey($direction, $ignoreList);
		if (isset($this->cache[$key])) {
			return $this->resolveTokenMatch($this->tkns, $this->cache[$key], $token);
		}

		$this->cache[$key] = $this->{$direction . 'tokenSubsetAtIdx'}($this->tkns, $this->ptr, $ignoreList);

		return $this->resolveTokenMatch($this->tkns, $this->cache[$key], $token);
	}

	protected function walkAndAccumulateCurlyBlock(array &$tkns): string{
		$count = 1;
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$ret .= $text;

			if (ST_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				++$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
		return $ret;
	}

	protected function walkAndAccumulateStopAt(array &$tkns, string $tknid): string{
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			if ($tknid == $id) {
				prev($tkns);
				break;
			}
			$ret .= $text;
		}
		return $ret;
	}

	protected function walkAndAccumulateStopAtAny(array &$tkns, array $tknids): array{
		$tknids = array_flip($tknids);
		$ret = '';
		$id = null;
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if (isset($tknids[$id])) {
				prev($tkns);
				break;
			}
			$ret .= $text;
		}
		return [$ret, $id];
	}

	protected function walkAndAccumulateUntil(array &$tkns, $tknid): string{
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$ret .= $text;
			if ($tknid == $id) {
				break;
			}
		}
		return $ret;
	}

	protected function walkAndAccumulateUntilAny(array &$tkns, array $tknids): array{
		$tknids = array_flip($tknids);
		$ret = '';
		while (list(, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$ret .= $text;
			if (isset($tknids[$id])) {
				break;
			}
		}
		return [$ret, $id];
	}

	protected function walkUntil($tknid): array{
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if ($id == $tknid) {
				return [$id, $text];
			}
		}
		return [null, null];
	}

	protected function walkUsefulRightUntil(array $tkns, int $idx, $tokens): int{
		$ignoreList = $this->resolveIgnoreList($this->ignoreFutileTokens);
		$tokens = array_flip($tokens);

		while ($idx > 0 && isset($tkns[$idx])) {
			$idx = $this->walkRight($tkns, $idx, $ignoreList);
			if (isset($tokens[$tkns[$idx][0]])) {
				return $idx;
			}
		}

		return count($tkns);
	}

	private function calculateCacheKey(string $direction, array $ignoreList): string {
		return $direction . "\x2" . implode('', $ignoreList);
	}

	private function resolveFoundToken($foundToken, $token): bool {
		if ($foundToken === $token) {
			return true;
		} elseif (is_array($token) && isset($foundToken[1]) && in_array($foundToken[0], $token)) {
			return true;
		} elseif (is_array($token) && !isset($foundToken[1]) && in_array($foundToken, $token)) {
			return true;
		} elseif (isset($foundToken[1]) && $foundToken[0] == $token) {
			return true;
		}

		return false;
	}

	private function resolveIgnoreList(array $ignoreList = []): array{
		if (!empty($ignoreList)) {
			return array_flip($ignoreList);
		}
		return [T_WHITESPACE => true];
	}

	private function resolveTokenMatch(array $tkns, int $idx, $token): bool {
		if (!isset($tkns[$idx])) {
			return false;
		}

		$foundToken = $tkns[$idx];
		return $this->resolveFoundToken($foundToken, $token);
	}

	private function walkLeft(array $tkns, int $idx, array $ignoreList): int{
		$i = $idx;
		while (--$i >= 0 && isset($ignoreList[$tkns[$i][0]]));
		return $i;
	}

	private function walkRight(array $tkns, int $idx, array $ignoreList): int{
		$i = $idx;
		$tknsSize = sizeof($tkns) - 1;
		while (++$i < $tknsSize && isset($ignoreList[$tkns[$i][0]]));
		return $i;
	}
}
