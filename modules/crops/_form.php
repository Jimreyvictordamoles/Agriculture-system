<?php
// Shared by add.php and edit.php.
// Expects: $values, $errors, $categories, $seasons, $submitLabel
?>
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" action="">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-6">
            <label for="crop_name" class="form-label">Crop name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="crop_name" name="crop_name"
                   value="<?= e($values['crop_name']) ?>" maxlength="100" required autofocus>
        </div>

        <div class="col-md-6">
            <label for="variety" class="form-label">Variety</label>
            <input type="text" class="form-control" id="variety" name="variety"
                   value="<?= e($values['variety']) ?>" maxlength="100">
        </div>

        <div class="col-md-4">
            <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="">-- Select category --</option>
                <?php foreach ($categories as $id => $name): ?>
                    <option value="<?= $id ?>" <?= (string) $values['category_id'] === (string) $id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label for="season_id" class="form-label">Recommended season <span class="text-danger">*</span></label>
            <select class="form-select" id="season_id" name="season_id" required>
                <option value="">-- Select season --</option>
                <?php foreach ($seasons as $id => $name): ?>
                    <option value="<?= $id ?>" <?= (string) $values['season_id'] === (string) $id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label for="growing_period_days" class="form-label">Growing period (days)</label>
            <input type="number" class="form-control" id="growing_period_days" name="growing_period_days"
                   value="<?= e($values['growing_period_days']) ?>" min="1" max="3650" step="1">
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><?= e($submitLabel) ?></button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
