<?php
/*
**	Rose\Ext\Shield\Required
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

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;

class Required extends Rule
{
	public function getName ()
	{
		return 'required';
	}

	public function validate ($name, &$val, $input, $output, $context)
	{
		$value = $this->getValue($context);

		if (is_string($val))
		{
			$val = Text::trim($val);
			$is_empty = Text::length($val) == 0;
		}
		else
			$is_empty = $val == null;

		if ($value === true) $value = 'true';
		if ($value === false) $value = 'false';

		$this->identifier = $value;

		switch ($value)
		{
			case 'true/null':
				if ($is_empty)
				{
					$val = null;
					throw new StopValidation();
				}

				break;

			case 'true/empty':
				if ($is_empty)
				{
					$val = '';
					throw new StopValidation();
				}

				break;

			case 'true':
				if ($is_empty)
					return false;

				break;

			case 'true/ignore': case 'false':
				if ($is_empty)
					throw new IgnoreField();

				break;
		}

		return true;
	}
};

Shield::registerRule('required', 'Rose\Ext\Shield\Required');
