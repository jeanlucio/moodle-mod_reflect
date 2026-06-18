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
 * AMD module for managing questions inline in the teacher view.
 *
 * @module     mod_reflect/manage_questions
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/** @type {int} Course module ID. */
let cmid = 0;

/**
 * Show the question form for adding or editing.
 *
 * @param {object|null} question Existing question data, or null for a new question.
 */
const showForm = (question = null) => {
    const form = document.getElementById('mod-reflect-question-form');
    const titleEl = document.getElementById('mod-reflect-form-title');
    const addBtn = document.getElementById('mod-reflect-add-question-btn');
    const idInput = document.getElementById('mod-reflect-form-questionid');
    const textInput = document.getElementById('mod-reflect-form-question');
    const typeSelect = document.getElementById('mod-reflect-form-responsetype');
    const gradeInput = document.getElementById('mod-reflect-form-maxgrade');

    if (question) {
        titleEl.textContent = document.querySelector('.mod-reflect-edit-btn')?.getAttribute('aria-label') || 'Edit';
        idInput.value = question.id;
        textInput.value = question.questionraw || '';
        typeSelect.value = question.responsetype || 'numeric';
        gradeInput.value = question.maxgrade || 0;
    } else {
        titleEl.textContent = addBtn.textContent.trim();
        idInput.value = '0';
        textInput.value = '';
        typeSelect.value = 'numeric';
        gradeInput.value = '0';
    }

    form.style.display = '';
    addBtn.style.display = 'none';
    textInput.focus();
};

/**
 * Hide the question form.
 */
const hideForm = () => {
    const form = document.getElementById('mod-reflect-question-form');
    const addBtn = document.getElementById('mod-reflect-add-question-btn');
    form.style.display = 'none';
    addBtn.style.display = '';
};

/**
 * Save or update a question via the web service.
 */
const saveQuestion = async() => {
    const idInput = document.getElementById('mod-reflect-form-questionid');
    const textInput = document.getElementById('mod-reflect-form-question');
    const typeSelect = document.getElementById('mod-reflect-form-responsetype');
    const gradeInput = document.getElementById('mod-reflect-form-maxgrade');

    const questionid = parseInt(idInput.value, 10);
    const question = textInput.value.trim();

    if (!question) {
        textInput.focus();
        return;
    }

    try {
        await Ajax.call([{
            methodname: 'mod_reflect_save_question',
            args: {
                cmid: cmid,
                questionid: questionid,
                question: question,
                questionformat: 1,
                responsetype: typeSelect.value,
                maxgrade: parseFloat(gradeInput.value) || 0,
            },
        }])[0];

        // Reload the page to reflect changes.
        window.location.reload();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Delete a question via the web service.
 *
 * @param {int} questionid The ID of the question to delete.
 * @param {string} confirmMessage The confirmation message to display.
 */
const deleteQuestion = async(questionid, confirmMessage) => {
    // eslint-disable-next-line no-alert
    if (!window.confirm(confirmMessage)) {
        return;
    }

    try {
        await Ajax.call([{
            methodname: 'mod_reflect_delete_question',
            args: {
                cmid: cmid,
                questionid: questionid,
            },
        }])[0];

        window.location.reload();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Initialize the question management module.
 *
 * @param {int} coursemoduleid Course module ID.
 */
export const init = (coursemoduleid) => {
    cmid = coursemoduleid;

    const container = document.getElementById('mod-reflect-teacher');
    if (!container) {
        return;
    }

    // Add question button.
    const addBtn = document.getElementById('mod-reflect-add-question-btn');
    if (addBtn) {
        addBtn.addEventListener('click', () => showForm());
    }

    // Cancel button.
    const cancelBtn = document.getElementById('mod-reflect-cancel-question-btn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', hideForm);
    }

    // Save button.
    const saveBtn = document.getElementById('mod-reflect-save-question-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', saveQuestion);
    }

    // Edit buttons (delegated).
    container.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.mod-reflect-edit-btn');
        if (editBtn) {
            const card = editBtn.closest('.mod-reflect-question-card');
            const questionText = card.querySelector('.mod-reflect-question-text');
            const badge = card.querySelector('.badge');
            const gradeText = card.querySelector('.text-muted.small');

            // Extract raw text from the rendered HTML.
            const rawtext = questionText ? questionText.textContent.trim() : '';

            // Determine responsetype from badge content.
            const numericLabel = container.querySelector('#mod-reflect-form-responsetype option[value="numeric"]');
            const isNumeric = badge && numericLabel && badge.textContent.trim() === numericLabel.textContent.trim();

            // Extract maxgrade from the display.
            let maxgrade = 0;
            if (gradeText) {
                const match = gradeText.textContent.match(/:\s*([\d.]+)/);
                if (match) {
                    maxgrade = parseFloat(match[1]);
                }
            }

            showForm({
                id: editBtn.dataset.questionid,
                questionraw: rawtext,
                responsetype: isNumeric ? 'numeric' : 'text',
                maxgrade: maxgrade,
            });
        }
    });

    // Delete buttons (delegated).
    container.addEventListener('click', (e) => {
        const delBtn = e.target.closest('.mod-reflect-delete-btn');
        if (delBtn) {
            const qid = parseInt(delBtn.dataset.questionid, 10);
            const msg = container.dataset.confirmdelete || 'Are you sure?';
            deleteQuestion(qid, msg);
        }
    });
};
