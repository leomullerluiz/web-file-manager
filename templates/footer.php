</div><!-- /#wrapper -->
<?php print_external('js-jquery'); ?>
<?php print_external('js-bootstrap'); ?>
<?php print_external('js-jquery-datatables'); ?>
<?php if (FM_USE_HIGHLIGHTJS && isset($_GET['view'])): ?>
    <?php print_external('js-highlightjs'); ?>
    <script>hljs.highlightAll(); var isHighlightingEnabled = true;</script>
<?php endif; ?>
<script>
    function template(html, options) {
        var re = /<\%([^\%>]+)?\%>/g, reExp = /(^( )?(if|for|else|switch|case|break|{|}))(.*)?/g,
            code = 'var r=[];\n', cursor = 0, match;
        var add = function (line, js) {
            js ? (code += line.match(reExp) ? line + '\n' : 'r.push(' + line + ');\n') :
                (code += line != '' ? 'r.push("' + line.replace(/"/g, '\\"') + '");\n' : '');
            return add;
        };
        while (match = re.exec(html)) {
            add(html.slice(cursor, match.index))(match[1], !0);
            cursor = match.index + match[0].length;
        }
        add(html.substr(cursor, html.length - cursor));
        code += 'return r.join("");';
        return new Function(code.replace(/[\r\t\n]/g, '')).apply(options);
    }

    function rename(e, t) {
        if (t) {
            $("#js-rename-from").val(t);
            $("#js-rename-to").val(t);
            $("#renameDailog").modal('show');
        }
    }

    function change_checkboxes(e, t) {
        for (var n = e.length - 1; n >= 0; n--) e[n].checked = "boolean" == typeof t ? t : !e[n].checked;
    }

    function get_checkboxes() {
        for (var e = document.getElementsByName("file[]"), t = [], n = e.length - 1; n >= 0; n--)
            (e[n].type = "checkbox") && t.push(e[n]);
        return t;
    }

    function select_all() { change_checkboxes(get_checkboxes(), !0); }
    function unselect_all() { change_checkboxes(get_checkboxes(), !1); }
    function invert_all() { change_checkboxes(get_checkboxes()); }
    function checkbox_toggle() { var e = get_checkboxes(); e.push(this); change_checkboxes(e); }

    function backup(e, t) {
        var n = new XMLHttpRequest,
            a = "path=" + e + "&file=" + t + "&token=" + window.csrf + "&type=backup&ajax=true";
        n.open("POST", "", !0);
        n.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        n.onreadystatechange = function () { 4 == n.readyState && 200 == n.status && toast(n.responseText); };
        n.send(a);
        return !1;
    }

    function toast(txt) {
        var x = document.getElementById("snackbar");
        x.innerHTML = txt;
        x.className = "show";
        setTimeout(function () { x.className = x.className.replace("show", ""); }, 3000);
    }

    function edit_save(e, t) {
        var n = "ace" == t ? editor.getSession().getValue() : document.getElementById("normal-editor").value;
        if (typeof n !== 'undefined' && n !== null) {
            var data = { ajax: true, content: n, type: 'save', token: window.csrf };
            $.ajax({
                type: "POST", url: window.location, data: JSON.stringify(data),
                contentType: "application/json; charset=utf-8",
                success: function (mes) { toast("Saved Successfully"); window.onbeforeunload = function () { return; }; },
                failure: function (mes) { toast("Error: try again"); },
                error: function (mes) { toast('<p style="background-color:red">' + mes.responseText + '</p>'); }
            });
        }
    }

    function show_new_pwd() { $(".js-new-pwd").toggleClass('hidden'); }

    function save_settings($this) {
        let form = $($this);
        $.ajax({
            type: form.attr('method'), url: form.attr('action'),
            data: form.serialize() + "&token=" + window.csrf + "&ajax=" + true,
            success: function (data) { if (data) { window.location.reload(); } }
        });
        return false;
    }

    function new_password_hash($this) {
        let form = $($this), $pwd = $("#js-pwd-result");
        $pwd.val('');
        $.ajax({
            type: form.attr('method'), url: form.attr('action'),
            data: form.serialize() + "&token=" + window.csrf + "&ajax=" + true,
            success: function (data) { if (data) { $pwd.val(data); } }
        });
        return false;
    }

    function upload_from_url($this) {
        let form = $($this), resultWrapper = $("div#js-url-upload__list");
        $.ajax({
            type: form.attr('method'), url: form.attr('action'),
            data: form.serialize() + "&token=" + window.csrf + "&ajax=" + true,
            beforeSend: function () {
                form.find("input[name=uploadurl]").attr("disabled", "disabled");
                form.find("button").hide();
                form.find(".lds-facebook").addClass('show-me');
            },
            success: function (data) {
                if (data) {
                    data = JSON.parse(data);
                    if (data.done) {
                        resultWrapper.append('<div class="alert alert-success row">Uploaded: ' + data.done.name + '</div>');
                        form.find("input[name=uploadurl]").val('');
                    } else if (data['fail']) {
                        resultWrapper.append('<div class="alert alert-danger row">Error: ' + data.fail.message + '</div>');
                    }
                    form.find("input[name=uploadurl]").removeAttr("disabled");
                    form.find("button").show();
                    form.find(".lds-facebook").removeClass('show-me');
                }
            },
            error: function (xhr) {
                form.find("input[name=uploadurl]").removeAttr("disabled");
                form.find("button").show();
                form.find(".lds-facebook").removeClass('show-me');
                console.error(xhr);
            }
        });
        return false;
    }

    function search_template(data) {
        var response = "";
        $.each(data, function (key, val) {
            response += '<li><a href="?p=' + val.path + '&view=' + val.name + '">' + val.path + '/' + val.name + '</a></li>';
        });
        return response;
    }

    function fm_search() {
        var searchTxt = $("input#advanced-search").val(),
            searchWrapper = $("ul#search-wrapper"),
            path = $("#js-search-modal").attr("href"),
            _html = "", $loader = $("div.lds-facebook");
        if (!!searchTxt && searchTxt.length > 2 && path) {
            var data = { ajax: true, content: searchTxt, path: path, type: 'search', token: window.csrf };
            $.ajax({
                type: "POST", url: window.location, data: data,
                beforeSend: function () { searchWrapper.html(''); $loader.addClass('show-me'); },
                success: function (data) {
                    $loader.removeClass('show-me');
                    data = JSON.parse(data);
                    if (data && data.length) {
                        _html = search_template(data);
                        searchWrapper.html(_html);
                    } else {
                        searchWrapper.html('<p class="m-2">No result found!<p>');
                    }
                },
                error: function (xhr) { $loader.removeClass('show-me'); searchWrapper.html('<p class="m-2">ERROR: Try again later!</p>'); }
            });
        } else {
            searchWrapper.html("OOPS: minimum 3 characters required!");
        }
    }

    function confirmDailog(e, id, title, content, action) {
        e.preventDefault();
        id = id || 0; title = title || "Action"; content = content || ""; action = action || null;
        const tplObj = { id, title, content: decodeURIComponent(content.replace(/\+/g, ' ')), action };
        let tpl = $("#js-tpl-confirm").html();
        $(".modal.confirmDailog").remove();
        $('#wrapper').append(template(tpl, tplObj));
        const $confirmDailog = $("#confirmDailog-" + tplObj.id);
        $confirmDailog.modal('show');
        return false;
    }

    !function (s) {
        s.previewImage = function (e) {
            var o = s(document), t = ".previewImage",
                a = s.extend({
                    xOffset: 20, yOffset: -20, fadeIn: "fast",
                    css: { padding: "5px", border: "1px solid #cccccc", "background-color": "#fff" },
                    eventSelector: "[data-preview-image]", dataKey: "previewImage", overlayId: "preview-image-plugin-overlay"
                }, e);
            return o.off(t),
                o.on("mouseover" + t, a.eventSelector, function (e) {
                    s("p#" + a.overlayId).remove();
                    var o = s("<p>").attr("id", a.overlayId).css("position", "absolute").css("display", "none")
                        .append(s('<img class="c-preview-img">').attr("src", s(this).data(a.dataKey)));
                    a.css && o.css(a.css);
                    s("body").append(o);
                    o.css("top", e.pageY + a.yOffset + "px").css("left", e.pageX + a.xOffset + "px").fadeIn(a.fadeIn);
                }),
                o.on("mouseout" + t, a.eventSelector, function () { s("#" + a.overlayId).remove(); }),
                o.on("mousemove" + t, a.eventSelector, function (e) {
                    s("#" + a.overlayId).css("top", e.pageY + a.yOffset + "px").css("left", e.pageX + a.xOffset + "px");
                }), this;
        };
        s.previewImage();
    }(jQuery);

    $(document).ready(function () {
        var $table = $('#main-table'),
            tableLng = $table.find('th').length,
            _targets = (tableLng && tableLng == 7) ? [0, 4, 5, 6] : tableLng == 5 ? [0, 4] : [3];
        if ($table.length) {
            mainTable = $('#main-table').DataTable({
                paging: false, info: false, order: [],
                columnDefs: [{ targets: _targets, orderable: false }]
            });
            $('#search-addon').on('keyup', function () { mainTable.search(this.value).draw(); });
        }

        $("input#advanced-search").on('keyup', function (e) { if (e.keyCode === 13) { fm_search(); } });
        $('#search-addon3').on('click', function () { fm_search(); });

        $(".fm-upload-wrapper .card-header-tabs").on("click", 'a', function (e) {
            e.preventDefault();
            let target = $(this).data('target');
            $(".fm-upload-wrapper .card-header-tabs a").removeClass('active');
            $(this).addClass('active');
            $(".fm-upload-wrapper .card-tabs-container").addClass('hidden');
            $(target).removeClass('hidden');
        });
    });
</script>

<?php if (isset($_GET['edit']) && isset($_GET['env']) && FM_EDIT_FILE && !FM_READONLY):
    $ext = pathinfo($_GET["edit"], PATHINFO_EXTENSION);
    $ext = $ext == "js" ? "javascript" : $ext;
    ?>
    <?php print_external('js-ace'); ?>
    <script>
        var editor = ace.edit("editor");
        editor.getSession().setMode({ path: "ace/mode/<?php echo fm_enc($ext); ?>", inline: true });
        editor.setShowPrintMargin(false);
        function ace_commend(cmd) { editor.commands.exec(cmd, editor); }
        editor.commands.addCommands([{
            name: 'save',
            bindKey: { win: 'Ctrl-S', mac: 'Command-S' },
            exec: function (editor) { edit_save(this, 'ace'); }
        }]);

        $(function () {
            $(".js-ace-toolbar").on("click", 'button', function (e) {
                e.preventDefault();
                let cmdValue = $(this).attr("data-cmd"),
                    editorOption = $(this).attr("data-option");
                if (cmdValue && cmdValue != "none") {
                    ace_commend(cmdValue);
                } else if (editorOption) {
                    if (editorOption == "fullscreen") {
                        (editor.container.requestFullScreen ? editor.container.requestFullScreen() :
                            editor.container.mozRequestFullScreen ? editor.container.mozRequestFullScreen() :
                                editor.container.webkitRequestFullScreen ? editor.container.webkitRequestFullScreen() :
                                    editor.container.msRequestFullscreen && editor.container.msRequestFullscreen());
                    } else if (editorOption == "wrap") {
                        editor.getSession().setUseWrapMode(!editor.getSession().getUseWrapMode());
                    }
                }
            });
            $("select#js-ace-mode, select#js-ace-theme, select#js-ace-fontSize").on("change", function (e) {
                e.preventDefault();
                let selectedValue = $(this).val(), selectionType = $(this).attr("data-type");
                if (selectedValue && selectionType == "mode") editor.getSession().setMode(selectedValue);
                else if (selectedValue && selectionType == "theme") editor.setTheme(selectedValue);
                else if (selectedValue && selectionType == "fontSize") editor.setFontSize(parseInt(selectedValue));
            });
        });
    </script>
<?php endif; ?>
<div id="snackbar"></div>
</body>

</html>