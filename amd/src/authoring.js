// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The Validate control on the library authoring form.
 *
 * Runs the author's reference solution against the test case rows as they
 * currently stand in the form, before the draft is saved. An author cannot tell
 * by reading whether an expected value is right; running it is the only way to
 * know, and knowing before publishing is the entire point.
 *
 * The activity form carries the same control against its own web service. This
 * is deliberately a near copy: the two forms share field names and a runner, so
 * an author meets the same check wherever they author.
 *
 * @module     local_saylorcode/authoring
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

/** @type {Object} Selectors used by this module. */
const SELECTORS = {
    BUTTON: '[data-action="saylorcode-validate"]',
    RESULT: '[data-region="validate-result"]',
    PROFILE: '[name="profileid"]',
    ENTRY: '[name="entryfilename"]',
    SOLUTION: '[name="referencesolution"]',
};

/**
 * Read the test case rows out of the form.
 *
 * @returns {Array} Test cases in the shape the service expects.
 */
const collectCases = () => {
    const expected = Array.from(document.querySelectorAll('[name^="tcexpected["]'));

    return expected.map((el, index) => {
        const at = (prefix) => document.querySelector(`[name="${prefix}[${index}]"]`);
        const value = (prefix) => {
            const field = at(prefix);
            return field ? field.value : '';
        };
        const checked = (prefix) => {
            const field = at(prefix);
            return field ? field.value === '1' : true;
        };

        return {
            id: `T${index + 1}`,
            name: value('tcname'),
            stdin: value('tcstdin'),
            expected: el.value,
            ispublic: checked('tcpublic'),
            weight: parseFloat(value('tcweight')) || 1,
            feedback: value('tcfeedback'),
        };
    }).filter((c) => c.expected.trim() !== '' || c.name.trim() !== '');
};

/**
 * Read one field's value from the form.
 *
 * @param {string} selector The field selector.
 * @returns {string} The value, or an empty string.
 */
const fieldValue = (selector) => {
    const el = document.querySelector(selector);
    return el ? el.value : '';
};

/**
 * Render the report.
 *
 * @param {HTMLElement} target Where the report goes.
 * @param {Object} report The service response.
 */
const render = (target, report) => {
    target.textContent = '';

    const summary = document.createElement('p');
    summary.className = `saylorcode-validate-summary ${report.valid ? 'text-success' : 'text-danger'}`;
    summary.textContent = `${report.valid ? '✓' : '✗'} ${report.summary}`;
    target.appendChild(summary);

    if (report.compileroutput) {
        const pre = document.createElement('pre');
        pre.className = 'saylorcode-validate-compiler';
        // Assigned as text: this is compiler output, not markup.
        pre.textContent = report.compileroutput;
        target.appendChild(pre);
    }

    if (!report.results.length) {
        return;
    }

    const list = document.createElement('ul');
    list.className = 'saylorcode-validate-list';

    report.results.forEach((result) => {
        const item = document.createElement('li');
        item.className = result.passed ? 'saylorcode-validate-pass' : 'saylorcode-validate-fail';

        const name = document.createElement('span');
        // Symbol and words, never colour alone.
        name.textContent = `${result.passed ? '✓' : '✗'} ${result.name}`;
        item.appendChild(name);

        if (!result.passed) {
            const detail = document.createElement('div');
            detail.className = 'saylorcode-validate-detail';

            const expected = document.createElement('div');
            expected.textContent = `expected: ${JSON.stringify(result.expected)}`;
            const actual = document.createElement('div');
            actual.textContent = `actual:   ${JSON.stringify(result.actual)}`;

            detail.appendChild(expected);
            detail.appendChild(actual);
            item.appendChild(detail);
        }

        list.appendChild(item);
    });

    target.appendChild(list);
};

/**
 * Wire up the Validate control.
 */
export const init = () => {
    const button = document.querySelector(SELECTORS.BUTTON);
    const target = document.querySelector(SELECTORS.RESULT);

    if (!button || !target) {
        return;
    }

    button.addEventListener('click', (e) => {
        e.preventDefault();

        button.disabled = true;

        // The status string and the validation run in parallel, and on an
        // uncached page the string can arrive second. An immediate report,
        // such as "no reference solution", would then be overwritten by
        // "Running the reference solution..." and left there for good, with
        // the button enabled again beneath it.
        let reported = false;

        getString('validaterunning', 'local_saylorcode').then((text) => {
            if (!reported) {
                target.textContent = text;
            }
            return text;
        }).catch(Notification.exception);

        // Native promise: jQuery Deferred has no finally, so building the chain
        // on one throws and the button below is never re-enabled.
        Promise.resolve(Ajax.call([{
            methodname: 'local_saylorcode_validate_exercise',
            args: {
                profileid: fieldValue(SELECTORS.PROFILE),
                entryfilename: fieldValue(SELECTORS.ENTRY),
                referencesolution: fieldValue(SELECTORS.SOLUTION),
                testcases: JSON.stringify(collectCases()),
            },
        }])[0]).then((report) => {
            reported = true;
            render(target, report);
            return report;
        }).catch((error) => {
            reported = true;
            target.textContent = error.message || String(error);
        }).finally(() => {
            button.disabled = false;
        });
    });
};
