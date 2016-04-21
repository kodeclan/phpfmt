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

final class ExtraCommaInArray extends FormatterPass {
	const ST_SHORT_ARRAY_OPEN = 'SHORT_ARRAY_OPEN';

	public function candidate(string $source, array $foundTokens): bool {
		if (isset($foundTokens[T_ARRAY]) || isset($foundTokens[ST_BRACKET_OPEN])) {
			return true;
		}

		return false;
	}

	public function format(string $source): string{
		$this->tkns = token_get_all($source);

		// It scans for possible blocks (parentheses, bracket and curly)
		// and keep track of which block is actually being addressed. If
		// it is an long array block (T_ARRAY) or short array block ([])
		// adds the missing comma in the end.
		$contextStack = [];
		$touchedBracketOpen = false;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case ST_BRACKET_OPEN:
				$touchedBracketOpen = true;
				$found = ST_BRACKET_OPEN;
				if ($this->isShortArray()) {
					$found = self::ST_SHORT_ARRAY_OPEN;
				}
				$contextStack[] = $found;
				break;

			case ST_BRACKET_CLOSE:
				if (isset($contextStack[0]) && !$this->leftTokenIs(ST_BRACKET_OPEN)) {
					if (self::ST_SHORT_ARRAY_OPEN == end($contextStack) && ($this->hasLnLeftToken() || $this->hasLnBefore()) && !$this->leftUsefulTokenIs(ST_COMMA)) {
						$prevTokenIdx = $this->leftUsefulTokenIdx();
						list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
						if (T_END_HEREDOC != $tknId && ST_BRACKET_OPEN != $tknId) {
							$this->tkns[$prevTokenIdx] = [$tknId, $tknText . ','];
						}
					} elseif (self::ST_SHORT_ARRAY_OPEN == end($contextStack) && !($this->hasLnLeftToken() || $this->hasLnBefore()) && $this->leftUsefulTokenIs(ST_COMMA)) {
						$prevTokenIdx = $this->leftUsefulTokenIdx();
						list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
						$this->tkns[$prevTokenIdx] = [$tknId, rtrim($tknText, ',')];
					}
					array_pop($contextStack);
					break;
				}
				$touchedBracketOpen = false;
				break;

			case ST_PARENTHESES_OPEN:
				$found = ST_PARENTHESES_OPEN;
				if ($this->leftUsefulTokenIs(T_STRING)) {
					$found = T_STRING;
				} elseif ($this->leftUsefulTokenIs(T_ARRAY)) {
					$found = T_ARRAY;
				}
				$contextStack[] = $found;
				break;

			case ST_PARENTHESES_CLOSE:
				if (isset($contextStack[0])) {
					if (T_ARRAY == end($contextStack) && ($this->hasLnLeftToken() || $this->hasLnBefore()) && !$this->leftUsefulTokenIs(ST_COMMA)) {
						$prevTokenIdx = $this->leftUsefulTokenIdx();
						list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
						if (T_END_HEREDOC != $tknId && ST_PARENTHESES_OPEN != $tknId) {
							$this->tkns[$prevTokenIdx] = [$tknId, $tknText . ','];
						}
					} elseif (T_ARRAY == end($contextStack) && !($this->hasLnLeftToken() || $this->hasLnBefore()) && $this->leftUsefulTokenIs(ST_COMMA)) {
						$prevTokenIdx = $this->leftUsefulTokenIdx();
						list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
						$this->tkns[$prevTokenIdx] = [$tknId, rtrim($tknText, ',')];
					}
					array_pop($contextStack);
				}
				break;

			default:
				$touchedBracketOpen = false;
				break;
			}
			$this->tkns[$this->ptr] = [$id, $text];
		}
		return $this->renderLight();
	}
}
