<?php
/*
**	Rose\Ext\Shield\Presence
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
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;

class Presence extends Rule
{
	public function getName ()
	{
		return 'presence';
	}

	public function validate ($name, &$val, $input, $output, $context)
	{
		$value = $this->getValue($context);
		$this->identifier = $value;

		if ($value === true) $value = 'true';
		if ($value === false) $value = 'false';

		switch ($value)
		{
			case 'true/null':
			case 'true|null':
				if (!$input->has($name))
				{
					$val = null;
					throw new StopValidation();
				}

				break;

			case 'true/empty':
			case 'true|empty':
				if (!$input->has($name))
				{
					$val = '';
					throw new StopValidation();
				}

				break;

			case 'true':
				if (!$input->has($name))
					return false;

				break;

			case 'true/ignore': case 'false':
			case 'true|ignore':
				if (!$input->has($name))
					throw new IgnoreField();

				break;
		}

		return true;
	}
};

Shield::registerRule('presence', 'Rose\Ext\Shield\Presence');
