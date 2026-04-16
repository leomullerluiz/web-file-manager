<?php
/**
 * Admin User Create/Edit Form Template
 * POST processing is handled in index.php before any output.
 * $user_form_errors and $user_form_data are set there on validation failure.
 */
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user    = $edit_id ? users_get_by_id($edit_id) : null;
$is_edit = $user !== null;

// Use data from index.php POST handler if available, else defaults
$form_errors = isset($user_form_errors) ? $user_form_errors : [];

$clients_base = FM_CLIENTS_DIR . DIRECTORY_SEPARATOR;

if (isset($user_form_data)) {
    $form_data = [
        'username'    => $user_form_data['username'],
        'email'       => $user_form_data['email'],
        'role'        => $user_form_data['role'],
        'directories' => $user_form_data['dirs_raw'], // already just the folder name
    ];
} else {
    // For edit: extract only the folder name from the stored full path
    $dir_full = $is_edit && !empty($user['directories']) ? $user['directories'][0] : '';
    $dir_name = $dir_full ? basename($dir_full) : '';

    $form_data = [
        'username'    => $is_edit ? $user['username'] : '',
        'email'       => $is_edit ? $user['email']    : '',
        'role'        => $is_edit ? $user['role']      : 'client',
        'directories' => $dir_name,
    ];
}
?>
<div class="col-md-8 offset-md-2 pt-3">
    <div class="card" data-bs-theme="<?php echo FM_THEME; ?>">
        <div class="card-header d-flex justify-content-between">
            <span><i class="fa fa-user"></i> <?php echo $is_edit ? lng('Edit User') : lng('New User') ?></span>
            <a href="?admin=users" class="text-danger"><i class="fa fa-times-circle-o"></i> <?php echo lng('Cancel') ?></a>
        </div>
        <div class="card-body">
            <?php if (!empty($form_errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($form_errors as $err): ?>
                        <div><?php echo fm_enc($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?admin=user_form<?php echo $is_edit ? '&id=' . $edit_id : '' ?>">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">

                <div class="mb-3">
                    <label for="username" class="form-label"><?php echo lng('Username') ?></label>
                    <input type="text" class="form-control" id="username" name="username"
                        value="<?php echo fm_enc($form_data['username']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label"><?php echo lng('Email') ?></label>
                    <input type="email" class="form-control" id="email" name="email"
                        value="<?php echo fm_enc($form_data['email']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">
                        <?php echo lng('Password') ?>
                        <?php if ($is_edit): ?>
                            <small class="text-muted">(<?php echo lng('Leave blank to keep current') ?>)</small>
                        <?php endif; ?>
                    </label>
                    <input type="password" class="form-control" id="password" name="password"
                        <?php echo $is_edit ? '' : 'required' ?> minlength="6" autocomplete="new-password">
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label"><?php echo lng('Role') ?></label>
                    <select class="form-select" id="role" name="role">
                        <option value="client" <?php echo $form_data['role'] == 'client' ? 'selected' : '' ?>><?php echo lng('client') ?></option>
                        <option value="admin" <?php echo $form_data['role'] == 'admin' ? 'selected' : '' ?>><?php echo lng('admin') ?></option>
                    </select>
                </div>

                <div class="mb-3" id="directories-field">
                    <label for="directories" class="form-label">
                        <?php echo lng('Directories') ?>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text font-monospace text-muted small" style="max-width:60%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo fm_enc($clients_base) ?>"><?php echo fm_enc($clients_base) ?></span>
                        <input type="text" class="form-control font-monospace" id="directories" name="directories"
                            placeholder="nome_do_cliente"
                            value="<?php echo fm_enc($form_data['directories']) ?>"
                            pattern="[^/\\\\]+" title="Apenas o nome da pasta, sem barras">
                    </div>
                    <div class="form-text">Digite apenas o nome da pasta do cliente dentro de <code>clients_files</code>.</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-check-circle"></i>
                        <?php echo $is_edit ? lng('Update User') : lng('Create User') ?>
                    </button>
                    <a href="?admin=users" class="btn btn-outline-secondary"><?php echo lng('Cancel') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    // Hide directories field and toggle required for admin role
    document.getElementById('role').addEventListener('change', function() {
        var dirField = document.getElementById('directories-field');
        var dirInput = document.getElementById('directories');
        var isAdmin  = this.value === 'admin';
        dirField.style.display = isAdmin ? 'none' : '';
        dirInput.required = !isAdmin;
    });
    // Trigger on load
    document.getElementById('role').dispatchEvent(new Event('change'));
</script>
