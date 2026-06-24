/**
 * @file Glue plugin: loads the tone-check UI component. CKEditor 5 only
 * discovers the plugin exported in index.js; this wires its parts together.
 */

import { Plugin } from 'ckeditor5/src/core';
import HelfiAiToneCheckUi from './helfiAiToneCheckUi';

export default class HelfiAiToneCheck extends Plugin {
	static get requires() {
		return [HelfiAiToneCheckUi];
	}

	static get pluginName() {
		return 'HelfiAiToneCheck';
	}
}
