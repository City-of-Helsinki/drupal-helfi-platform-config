/**
 * @file Glue plugin: loads the tone-check UI component. CKEditor 5 only
 * discovers the plugin exported in index.js; this wires its parts together.
 */

import { Plugin } from 'ckeditor5/src/core';
import aiToneCheckUi from './aiToneCheckUi';

export default class AiToneCheck extends Plugin {
	static get requires() {
		return [aiToneCheckUi];
	}

	static get pluginName() {
		return 'aiToneCheck';
	}
}
