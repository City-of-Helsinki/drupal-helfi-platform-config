/**
 * @file Load the editing and the UI parts of the plugin.
 */

import { Plugin } from 'ckeditor5/src/core';
import HelfiNbspEditing from './helfiNbspEditing';
import HelfiNbspUi from './helfiNbspUi';

export default class HelfiNbsp extends Plugin {
  static get requires() {
    return [HelfiNbspEditing, HelfiNbspUi];
  }
}
