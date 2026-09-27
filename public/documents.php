<?php
require_once __DIR__ . '/../src/layout.php';

$u  = require_login();
$id = (int)($_GET['listing'] ?? $_POST['listing'] ?? 0);
$l  = db_row('SELECT * FROM listings WHERE id = ?', [$id]);

if (!$l || (int)$l['user_id'] !== (int)$u['id']) {
    flash('You can only manage documents on your own listings.', 'warn');
    redirect('dashboard.php');
}

$uploadDir = __DIR__ . '/../storage/uploads';
$allowedMimes = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
];
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

/* ---- handle POST: publish, add or delete a document ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_POST['publish_listing'])) {
        if (!listing_can_publish($id)) {
            flash('Upload at least one core vehicle document before publishing.', 'error');
            redirect('documents.php?listing=' . $id);
        }
        db_exec("UPDATE listings SET status = 'live' WHERE id = ? AND user_id = ?", [$id, $u['id']]);
        flash('Listing published.');
        redirect('listing.php?id=' . $id);
    }

    if (isset($_POST['delete_doc'])) {
        $doc = db_row('SELECT * FROM documents WHERE id = ? AND listing_id = ?', [(int)$_POST['delete_doc'], $id]);
        if ($doc) {
            if ($doc['file_path'] && file_exists($uploadDir . '/' . $doc['file_path'])) {
                @unlink($uploadDir . '/' . $doc['file_path']);
            }
            db_exec('DELETE FROM documents WHERE id = ?', [$doc['id']]);
            flash('Document removed.');
        }
        redirect('documents.php?listing=' . $id);
    }

    $type  = $_POST['doc_type'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $note  = trim($_POST['note'] ?? '');

    $validTypes = array_merge(array_keys(CORE_DOCS), ['other']);
    if (!in_array($type, $validTypes, true)) {
        flash('Pick a document type.', 'error');
        redirect('documents.php?listing=' . $id);
    }
    if ($title === '') {
        $title = $type === 'other' ? 'Additional document' : CORE_DOCS[$type];
    }
    if (mb_strlen($title) > 160 || mb_strlen($note) > 500) {
        flash('Document title or note is too long.', 'error');
        redirect('documents.php?listing=' . $id);
    }

    $storedName = null;
    if (!empty($_FILES['file']['name'])) {
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            flash('Upload failed — try again.', 'error');
            redirect('documents.php?listing=' . $id);
        }
        if ((int)$file['size'] <= 0 || (int)$file['size'] > 5 * 1024 * 1024) {
            flash('File must be between 1 byte and 5 MB.', 'error');
            redirect('documents.php?listing=' . $id);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset($allowedMimes[$mime])) {
            flash('Only genuine PDF, JPG or PNG files are accepted.', 'error');
            redirect('documents.php?listing=' . $id);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $storedName)) {
            flash('Could not save the file.', 'error');
            redirect('documents.php?listing=' . $id);
        }
    }

    db_exec(
        'INSERT INTO documents (listing_id, doc_type, title, note, file_path) VALUES (?,?,?,?,?)',
        [$id, $type, $title, $note ?: null, $storedName]
    );
    flash($storedName ? 'Document uploaded.' : 'Document declared. Upload the file when you have it.');
    redirect('documents.php?listing=' . $id);
}

$docs = listing_docs($id);
$dos  = documents($docs);

page_header('Documents — ' . $l['make'] . ' ' . $l['model'], 'sell');
?>
<div class="wrap section-tight">
  <p class="small"><a href="listing.php?id=<?= $id ?>">← Back to listing</a></p>
  <h1>Add vehicle documents</h1>
  <p class="muted">
    <?= e($l['year'] . ' ' . $l['make'] . ' ' . $l['model']) ?> · <?= reg_plate($l['reg']) ?>
    · <?= status_badge($l['status']) ?>
  </p>

  <?php if ($l['status'] === 'draft'): ?>
    <div class="premium-note" style="margin:18px 0">
      <strong>This listing is private.</strong> Upload at least one core document file before publishing it.
      <?php if ($dos['present'] > 0): ?>
        <form method="post" class="inline-form" style="margin-left:10px">
          <?= csrf_field() ?>
          <input type="hidden" name="listing" value="<?= $id ?>">
          <button class="btn btn-primary btn-sm" name="publish_listing" value="1">Publish listing</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="detail-grid" style="margin-top:20px">
    <div>
      <div class="panel">
        <h3>Add a document</h3>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="listing" value="<?= $id ?>">
          <div class="field" style="margin-bottom:14px">
            <label for="doc_type">Category</label>
            <select id="doc_type" name="doc_type" required>
              <option value="">— Choose —</option>
              <?php foreach (CORE_DOCS as $key => $label): ?>
                <?php $slot = $dos['slots'][$key]; ?>
                <option value="<?= $key ?>">
                  <?= e($label) ?><?= $slot['verified'] ? ' ✓ verified' : ($slot['present'] ? ' ✓ uploaded' : ($slot['declared'] ? ' · declared' : '')) ?>
                </option>
              <?php endforeach; ?>
              <option value="other">Other (photos of receipts, extras…)</option>
            </select>
          </div>
          <div class="field" style="margin-bottom:14px">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" maxlength="160" placeholder="e.g. NCT certificate to 07/2027">
          </div>
          <div class="field" style="margin-bottom:14px">
            <label for="note">Note (optional)</label>
            <input type="text" id="note" name="note" maxlength="500" placeholder="e.g. Passed with no advisories">
          </div>
          <div class="field" style="margin-bottom:14px">
            <label for="file">File — PDF, JPG or PNG, max 5 MB (optional)</label>
            <input type="file" id="file" name="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
            <p class="small muted" style="margin:6px 0 0">
              A declaration is shown separately from an uploaded file and does not increase the completeness score.
            </p>
          </div>
          <button class="btn btn-primary" type="submit">Add document</button>
        </form>
      </div>

      <div class="todo-note" style="margin-top:18px">
        <strong>Verification:</strong> uploaded is not the same as verified. The verified stamp is reserved
        for evidence checked by a trusted workflow; the mechanic verification flow is still on the roadmap.
      </div>
    </div>

    <div>
      <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:baseline">
          <h3 style="margin:0">Documents status</h3>
          <span class="mono small"><?= $dos['present'] ?>/<?= $dos['total'] ?> uploaded</span>
        </div>
        <div style="margin:12px 0"><?= documents_meter($dos, true) ?></div>

        <?php if ($docs): ?>
          <ul class="doc-list">
            <?php foreach ($docs as $d): ?>
              <li>
                <div class="doc-row">
                  <div>
                    <strong><?= e($d['title']) ?></strong>
                    <span class="small muted"> · <?= e(CORE_DOCS[$d['doc_type']] ?? 'Other') ?></span>
                    <?php if ($d['verified'] && $d['file_path']): ?> <span class="stamp">Verified</span><?php endif; ?>
                    <?php if ($d['file_path']): ?>
                      <br><a class="small" href="document.php?id=<?= $d['id'] ?>" target="_blank" rel="noopener">View uploaded file →</a>
                    <?php else: ?>
                      <br><span class="small muted">Declared only — no file uploaded</span>
                    <?php endif; ?>
                  </div>
                  <form method="post" class="inline-form"
                        onsubmit="return confirm('Remove this document?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="listing" value="<?= $id ?>">
                    <button class="btn btn-danger btn-sm" name="delete_doc" value="<?= $d['id'] ?>">Remove</button>
                  </form>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted">Nothing added yet. Start with the NCT cert or service history.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>
