// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AMD module for autosaving student responses in mod_reflect.
 *
 * @module     mod_reflect/autosave
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

let cmid = 0;
let debounceTimers = {};
let savingText = '';
let savedText = '';

/**
 * Shows saving status indicator.
 * @param {HTMLElement} card
 */
const showSaving = (card) => {
    const status = card.querySelector('.mod-reflect-save-status');
    if (status) {
        status.classList.remove('d-none');
        status.querySelector('.fa').className = 'fa fa-circle-o-notch fa-spin';
        status.querySelector('.status-text').textContent = savingText;
    }
};

/**
 * Shows saved status indicator.
 * @param {HTMLElement} card
 */
const showSaved = (card) => {
    const status = card.querySelector('.mod-reflect-save-status');
    if (status) {
        status.querySelector('.fa').className = 'fa fa-check text-success';
        status.querySelector('.status-text').textContent = savedText;
        setTimeout(() => {
            status.classList.add('d-none');
        }, 3000);
    }
};

/**
 * Triggers the actual ajax save.
 * @param {int} questionId
 * @param {float|null} value
 * @param {string|null} text
 * @param {string|null} comment
 * @param {HTMLElement} card
 */
const doSave = (questionId, value, text, comment, card) => {
    showSaving(card);

    Ajax.call([{
        methodname: 'mod_reflect_save_response',
        args: {
            cmid: cmid,
            questionid: questionId,
            value: value,
            responsetext: text,
            comment: comment
        }
    }])[0].done(() => {
        showSaved(card);
    }).fail(Notification.exception);
};

/**
 * Handle slider input to update the badge immediately.
 * @param {Event} e
 */
const handleSliderInput = (e) => {
    const slider = e.target;
    const card = slider.closest('.mod-reflect-question-card');
    const badge = card.querySelector('.mod-reflect-slider-value');
    if (badge) {
        badge.textContent = slider.value;
    }
};

/**
 * Handle input changes with debounce for autosave.
 * @param {Event} e
 */
const handleChange = (e) => {
    const input = e.target;
    const isGlobalComment = input.classList.contains('mod-reflect-global-comment');

    let card;
    let questionId;
    let value = null;
    let text = null;
    let comment = null;

    if (isGlobalComment) {
        card = input.closest('.mod-reflect-comment-card');
        comment = input.value;
        // Bind the global comment to the first question available.
        const firstQuestionCard = document.querySelector('.mod-reflect-question-card');
        if (!firstQuestionCard) {
            return; // Nowhere to save if no questions exist.
        }
        questionId = parseInt(firstQuestionCard.dataset.questionid, 10);
    } else {
        card = input.closest('.mod-reflect-question-card');
        questionId = parseInt(card.dataset.questionid, 10);

        if (input.type === 'range') {
            value = parseFloat(input.value);
        } else {
            text = input.value;
        }
    }

    if (debounceTimers[questionId + (isGlobalComment ? '_comment' : '')]) {
        clearTimeout(debounceTimers[questionId + (isGlobalComment ? '_comment' : '')]);
    }

    debounceTimers[questionId + (isGlobalComment ? '_comment' : '')] = setTimeout(() => {
        doSave(questionId, value, text, comment, card);
    }, 800);
};

export const init = (id) => {
    cmid = id;

    const container = document.getElementById('mod-reflect-student');
    if (container) {
        savingText = container.dataset.saving || '';
        savedText = container.dataset.saved || '';
    }

    // Attach listeners to sliders
    document.querySelectorAll('.mod-reflect-slider').forEach(slider => {
        slider.addEventListener('input', handleSliderInput);
        slider.addEventListener('change', handleChange);
    });

    // Attach listeners to textareas
    document.querySelectorAll('.mod-reflect-textarea').forEach(ta => {
        ta.addEventListener('input', handleChange);
    });

    // Attach listener to global comment
    const globalComment = document.querySelector('.mod-reflect-global-comment');
    if (globalComment) {
        globalComment.addEventListener('input', handleChange);
    }
};
