<?php
/*
**	Rose\Ext\Shield\Extract
**
**	Copyright (c) 2019-2020, RedStar Technologies, All rights reserved.
**	https://rsthn.com/
**
**	THIS LIBRARY IS PROVIDED BY REDSTAR TECHNOLOGIES "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
**	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A 
**	PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL REDSTAR TECHNOLOGIES BE LIABLE FOR ANY
**	DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT 
**	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; 
**	OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, 
**	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
**	USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace Rose\Ext\Shield;

use Rose\Errors\ArgumentError;
use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Text;
use Rose\Strings;
use Rose\Regex;

class Extract extends Rule
{
	public function getName ()
	{
		return 'extract';
	}

	public function validate ($name, &$val, $input, $output, $context)
	{
		$value = $this->getValue($context);
		if (!$this->valueIsString() && $value[0] != '/' && $value[0] != '|')
		{
			$this->identifier = $value;

			$regex = Strings::getInstance()->regex->$value;
			if (!$regex) throw new ArgumentError('undefined_regex: '.$value);
		}
		else
			$regex = $value;

		$val = Regex::_extract ($regex, $val, '', 0);
		return true;
	}
};

Shield::registerRule('extract', 'Rose\Ext\Shield\Extract');
