<?php
// Shared by add.php and edit.php.
// Expects: $values, $errors, $submitLabel
?>
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger py-2"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" action="">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-6">
            <label for="first_name" class="form-label">First name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="first_name" name="first_name"
                   value="<?= e($values['first_name']) ?>" maxlength="50" required autofocus>
        </div>

        <div class="col-md-6">
            <label for="last_name" class="form-label">Last name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="last_name" name="last_name"
                   value="<?= e($values['last_name']) ?>" maxlength="50" required>
        </div>

        <div class="col-md-6">
            <label for="contact_number" class="form-label">Contact number <span class="text-danger">*</span></label>
            <input type="tel" class="form-control" id="contact_number" name="contact_number"
                   value="<?= e($values['contact_number']) ?>" maxlength="20"
                   placeholder="e.g. 09171234567" required>
        </div>

        <div class="col-md-6">
            <label for="date_registered" class="form-label">Date registered <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="date_registered" name="date_registered"
                   value="<?= e($values['date_registered']) ?>" max="<?= e(today_manila()) ?>" required>
        </div>

        <div class="col-12">
            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="address" name="address"
                   value="<?= e($values['address']) ?>" maxlength="255"
                   placeholder="Barangay, city / municipality, province" required>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><?= e($submitLabel) ?></button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
