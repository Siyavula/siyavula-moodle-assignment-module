define(['core/ajax'], function(Ajax) {

    /**
     * Send the grade update to Moodle via AJAX.
     *
     * @param {number} cmid      Course module ID.
     * @param {number} score     Assignment score (0-100).
     * @param {boolean} completed Whether the assignment is completed.
     */
    function triggerGradeUpdate(cmid, score, completed) {
        Ajax.call([{
            methodname: 'mod_siyavulaassignment_update_grade',
            args: {cmid: cmid, score: score, completed: completed},
        }])[0].catch(function(err) {
            console.warn('Siyavula Assignment: grade update failed', err);
        });
    }

    return {
        /**
         * Bind renderer events on a fully-initialised assignment API instance.
         *
         * - activityProgressUpdate: updates the badge with "current/total"
         * - assignmentComplete: sends final score to the Moodle gradebook
         *
         * @param {Object} api  The assignment API instance returned by createActivity.
         * @param {number} cmid Course module ID.
         */
        bindEvents: function(api, cmid) {
            if (!api || !api.renderer) {
                console.warn('Siyavula Assignment: cannot bind events, renderer not available');
                return;
            }

            // Update progress badge as the learner advances through questions.
            var badge = api.renderer.container.querySelector('.sv-session-badge--assignment');
            api.renderer.on('activityProgressUpdate', function(data) {
                if (badge) {
                    badge.textContent = (data.current_index + 1) + '/' + data.question_count;
                }
            });

            // Emit once to set the initial badge value.
            if (api.currentActivity && api.currentActivity.progress) {
                api.renderer.emit('activityProgressUpdate', api.currentActivity.progress);
            }

            // When the assignment is complete, show a completion message and send score to gradebook.
            api.renderer.on('assignmentComplete', function(data) {
                var score = (data.correct_count / data.question_count) * 100;
                triggerGradeUpdate(cmid, score, true);

                var feedback = '';
                var finalFeedback = '';

                if (score === 0) {
                    feedback = "Great effort! You've completed the assignment.";
                    finalFeedback = "You didn't get any questions correct this time. Try it again to improve!";
                } else if (score < 50) {
                    feedback = "Great effort! You've completed the assignment.";
                    finalFeedback = 'Your overall result is ' + Math.round(score) + '%.';
                } else if (score < 70) {
                    feedback = "Well done! You've completed the assignment.";
                    finalFeedback = 'Your overall result is ' + Math.round(score) + '%.';
                } else if (score < 100) {
                    feedback = "Excellent! You've completed the assignment.";
                    finalFeedback = 'Your overall result is ' + Math.round(score) + '%.';
                } else {
                    feedback = "Awesome! You've completed the assignment.";
                    finalFeedback = 'You answered every question correctly and got 100%!';
                }

                var completionHtml = '<div class="assignment-completion-message">' +
                    '<div class="assignment-completion-message-title">' + feedback + '</div>' +
                    '<div class="assignment-completion-message-description">' + finalFeedback + '</div>' +
                    '</div>';

                var svContainer = api.renderer.container.querySelector('.sv');
                if (!svContainer) {
                    svContainer = api.renderer.container;
                }
                svContainer.insertAdjacentHTML('beforeend', completionHtml);

                var actions = api.renderer.container.querySelector('.sv-form__actions');
                if (actions) {
                    actions.style.display = 'none';
                }
            });
        }
    };

});
