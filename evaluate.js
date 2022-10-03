/*
 * Copyright (C) 2015 onwards Catalyst IT
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author  Eugene Venter <eugene@catalyst.net.nz>
 * @package mod_ojt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 /* eslint-env jquery */
 /* eslint no-console: "warn" */
M.mod_ojt_evaluate = M.mod_ojt_evaluate || {

    Y: null,

    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args) {
        // save a reference to the Y instance (all of its dependencies included)
        this.Y = Y;
        var ojtobj = this;

        // if defined, parse args into this module's config object
        if (args) {
            var jargs = Y.JSON.parse(args);
            for (var a in jargs) {
                if (Y.Object.owns(jargs, a)) {
                    this.config[a] = jargs[a];
                }
            }
        }

        // check jQuery dependency is available
        if (typeof $ === 'undefined') {
            throw new Error('M.mod_ojt_evaluate.init()-> jQuery dependency required for this module.');
        }

        var config = this.config;

        // Init ojt topic item selection toggle
        $('.mod-ojt-topic-item-selection-label').on('click', function() {
            var topicitemselection = $(this);
            var itemid = topicitemselection.parent().attr('ojt-item-id');
            $.ajax({
                url: M.cfg.wwwroot + '/mod/ojt/evaluatesave.php',
                type: 'POST',
                data: {
                    'sesskey': M.cfg.sesskey,
                    'action': 'complete',
                    'bid': config.ojtid,
                    'userid': config.userid,
                    'id': itemid
                },
                success: function(data) {
                    // Update the topic's completion too.
                    ojtobj.setTopicStatusIcon(data.item.status, $('#ojt-topic-' + data.item.topicid + ' .ojt-topic-status'));

                    // Update modified string.
                    $('.mod-ojt-modifiedstr[ojt-item-id=' + itemid + ']').html(data.modifiedstr);
                },
                error: function(data) {
                    console.log(data);
                    alert('Error saving completion...');
                }
            });
        });

        // Init ojt completion toggles.
        $('.ojt-completion-toggle').on('click', function() {
            var completionimg = $(this);
            var itemid = $(this).closest('.ojt-eval-actions').attr('ojt-item-id');
            $.ajax({
                url: M.cfg.wwwroot + '/mod/ojt/evaluatesave.php',
                type: 'POST',
                data: {
                    'sesskey': M.cfg.sesskey,
                    'action': 'togglecompletion',
                    'bid': config.ojtid,
                    'userid': config.userid,
                    'id': itemid
                },
                beforeSend: function() {
                    ojtobj.replaceIcon(completionimg, 'loading');
                },
                success: function(data) {
                    if (data.item.status == config.OJT_COMPLETE) {
                        ojtobj.replaceIcon(completionimg, 'completion-manual-y');
                    } else {
                        ojtobj.replaceIcon(completionimg, 'completion-manual-n');
                    }

                    // Update the topic's completion too.
                    ojtobj.setTopicStatusIcon(data.topic.status, $('#ojt-topic-' + data.topic.topicid + ' .ojt-topic-status'));

                    // Update modified string.
                    $('.mod-ojt-modifiedstr[ojt-item-id=' + itemid + ']').html(data.modifiedstr);

                    $(completionimg).next('.ojt-completion-comment').focus();
                },
                error: function(data) {
                    console.log(data);
                    alert('Error saving completion...');
                }
            });
        });

        // Init comment inputs
        $('.ojt-completion-comment').change(function() {
            var commentinput = this;
            var itemid = $(this).attr('ojt-item-id');
            $.ajax({
                url: M.cfg.wwwroot + '/mod/ojt/evaluatesave.php',
                type: 'POST',
                data: {
                    'sesskey': M.cfg.sesskey,
                    'action': 'savecomment',
                    'bid': config.ojtid,
                    'userid': config.userid,
                    'id': itemid,
                    'comment': $(commentinput).val()
                },
                success: function(data) {

                    // Update comment text box, so we can get the date in there too
                    $(commentinput).val(data.item.comment);
                    // Update the comment print box
                    $('.ojt-completion-comment-print[ojt-item-id=' + itemid + ']').html(data.item.comment);

                    $('.mod-ojt-modifiedstr[ojt-item-id=' + itemid + ']').html(data.modifiedstr);
                },
                error: function(data) {
                    console.log(data);
                    alert('Error saving comment...');
                }
            });
        });

        $('span.mod-ojt-item-type-select input').change(function() {
            var commentinput = this;
            var itemid = $(this).parent().attr('ojt-item-id');

            $.ajax({
                url: M.cfg.wwwroot+'/mod/ojt/evaluatesave.php',
                type: 'POST',
                data: {
                    'sesskey' : M.cfg.sesskey,
                    'action': 'savecomment',
                    'bid': config.ojtid,
                    'userid': config.userid,
                    'id': itemid,
                    'comment': $(commentinput).val()
                },
                success: function(data) {
                    // Unlike text comments, no update for the value here.
                    // Update the comment print box
                    $('.ojt-completion-comment-print[ojt-item-id='+itemid+']').html(data.item.comment);
                    $('.mod-ojt-modifiedstr[ojt-item-id='+itemid+']').html(data.modifiedstr);
                    // Trigger click if checkbox icon is unchecked.
                    $selector = $(".ojt-eval-actions[ojt-item-id='"+itemid+"'] span[data-flex-icon]");
                    if ($selector.length && $selector[0].classList.contains("fa-square-o")) {
                        $selector.trigger("click");
                    }
                },
                error: function(data) {
                    console.log(data);
                    alert('Error saving comment...');
                }
            });
        });

        // Init completion witness toggle.
        $('.ojt-witness-toggle').on('click', function() {
            var completionimg = $(this);
            var itemid = $(this).closest('.ojt-witness-item').attr('ojt-item-id');
            $.ajax({
                url: M.cfg.wwwroot + '/mod/ojt/witnesssave.php',
                type: 'POST',
                data: {
                    'sesskey': M.cfg.sesskey,
                    'bid': config.ojtid,
                    'userid': config.userid,
                    'id': itemid
                },
                beforeSend: function() {
                    ojtobj.replaceIcon(completionimg, 'loading');
                },
                success: function(data) {
                    if (data.item.witnessedby > 0) {
                        ojtobj.replaceIcon(completionimg, 'completion-manual-y');
                    } else {
                        ojtobj.replaceIcon(completionimg, 'completion-manual-n');
                    }

                    // Update the topic's completion too.
                    ojtobj.setTopicStatusIcon(data.topic.status, $('#ojt-topic-' + data.topic.topicid + ' .ojt-topic-status'));

                    // Update modified string.
                    $('.mod-ojt-witnessedstr[ojt-item-id=' + itemid + ']').html(data.modifiedstr);
                },
                error: function(data) {
                    console.log(data);
                    alert('Error saving witness data...');
                }
            });
        });

        // Init topic signoffs
        $('.ojt-topic-signoff-toggle').on('click', function() {
            var signoffimg = $(this);
            var topicid = $(this).closest('.mod-ojt-topic-signoff');
            var topicid = $(topicid).attr('ojt-topic-id');
            $.ajax({
                url: M.cfg.wwwroot + '/mod/ojt/evaluatesignoff.php',
                type: 'POST',
                data: {
                    'sesskey': M.cfg.sesskey,
                    'bid': config.ojtid,
                    'userid': config.userid,
                    'id': topicid
                },
                beforeSend: function() {
                    ojtobj.replaceIcon(signoffimg, 'loading');
                },
                success: function(data) {
                    if (data.topicsignoff.signedoff) {
                        ojtobj.replaceIcon(signoffimg, 'completion-manual-y');
                    } else {
                        ojtobj.replaceIcon(signoffimg, 'completion-manual-n');
                    }

                    // Update the topic's completion too.
                    ojtobj.setTopicStatusIcon(data.topic.status, $('#ojt-topic-' + data.topic.topicid + ' .ojt-topic-status'));

                    $('.mod-ojt-topic-signoff[ojt-topic-id=' + topicid + '] .mod-ojt-topic-modifiedstr').html(data.modifiedstr);
                },
                error: function(data) {
                    console.log(data);
                    alert('Error saving signoff...');
                }
            });
        });
    },  // init

    replaceIcon: function(icon, newiconname) {
        require(['core/templates'], function(templates) {
            templates.renderIcon(newiconname).done(function(html) {
                icon.attr('data-flex-icon', $(html).attr('data-flex-icon'));
                icon.attr('class', $(html).attr('class'));
            });
        });

    },

    setTopicStatusIcon: function(topicstatus, statuscontainer) {
        var iconname = 'times-danger';
        if (topicstatus == this.config.OJT_COMPLETE) {
            iconname = 'check-success';
        } else if (topicstatus == this.config.OJT_REQUIREDCOMPLETE) {
            iconname = 'check-warning';
        }
        require(['core/templates'], function(templates) {
            templates.renderIcon(iconname).done(function(html) {
                statuscontainer.html(html);
            });
        });
    },
};
