<?php
require_once '../includes/config.php';

// Redirect if not logged in as busker
if (!isset($_SESSION['busker_id'])) {
    header('Location: index.php');
    exit();
}

$busker_id = $_SESSION['busker_id'];
$success = '';
$error = '';

// Handle image uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    $upload_type = $_POST['upload_type'];
    $target_dir = '../assets/images/busker_uploads/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $file = $_FILES['image_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $upload_type . '_busker_' . $busker_id . '_' . time() . '.' . $ext;
        $target_file = $target_dir . $filename;
        // Delete old image if exists
        $col = $upload_type === 'profile' ? 'profile_image' : 'background_image';
        $stmt = $conn->prepare("SELECT $col FROM busker WHERE busker_id = ?");
        $stmt->execute([$busker_id]);
        $old_img = $stmt->fetchColumn();
        if ($old_img && strpos($old_img, 'assets/images/busker_uploads/') === 0) {
            $old_path = __DIR__ . '/../' . $old_img;
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $db_path = 'assets/images/busker_uploads/' . $filename;
            $stmt = $conn->prepare("UPDATE busker SET $col = ? WHERE busker_id = ?");
            $stmt->execute([$db_path, $busker_id]);
            header('Location: profile.php?success=' . $upload_type . '_image');
            exit();
        } else {
            header('Location: profile.php?error=upload_fail');
            exit();
        }
    } else {
        header('Location: profile.php?error=upload_error');
        exit();
    }
}

// Handle profile update (text fields, genres, equipment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            UPDATE busker
            SET band_name = ?, name = ?, contact_number = ?, address = ?, birthday = ?, has_equipment = ?
            WHERE busker_id = ?
        ");
        $stmt->execute([
            $_POST['band_name'],
            $_POST['name'],
            $_POST['contact_number'],
            $_POST['address'],
            $_POST['birthday'],
            isset($_POST['has_equipment']) ? 1 : 0,
            $busker_id
        ]);
        $stmt = $conn->prepare("DELETE FROM busker_genre WHERE busker_id = ?");
        $stmt->execute([$busker_id]);
        if (!empty($_POST['genres'])) {
            $insert_genre_stmt = $conn->prepare("INSERT INTO busker_genre (busker_id, genre_id) VALUES (?, ?)");
            foreach ($_POST['genres'] as $genre_id) {
                $insert_genre_stmt->execute([$busker_id, $genre_id]);
            }
        }
        $conn->commit();
        header('Location: profile.php?success=profile_update');
        exit();
    } catch (PDOException $e) {
        header('Location: profile.php?error=profile_update');
        exit();
    }
}
// Handle add equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_equipment'])) {
    try {
        if (empty($_POST['equipment_name'])) throw new Exception("Equipment name is required");
        $stmt = $conn->prepare("
            INSERT INTO busker_equipment (busker_id, equipment_name, quantity, eq_condition)
            VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $busker_id,
            $_POST['equipment_name'],
            $_POST['quantity'] ?? '',
            $_POST['eq_condition'] ?? ''
        ]);
        header('Location: profile.php?success=equipment_add');
        exit();
    } catch (Exception $e) {
        header('Location: profile.php?error=equipment_add');
        exit();
    }
}
// Handle delete equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_equipment'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM busker_equipment WHERE equipment_id = ? AND busker_id = ?");
        $stmt->execute([$_POST['equipment_id'], $busker_id]);
        header('Location: profile.php?success=equipment_delete');
        exit();
    } catch (PDOException $e) {
        header('Location: profile.php?error=equipment_delete');
        exit();
    }
}
// Handle profile image removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_profile_image'])) {
    // Delete old profile image file
    $stmt = $conn->prepare("SELECT profile_image FROM busker WHERE busker_id = ?");
    $stmt->execute([$busker_id]);
    $old_img = $stmt->fetchColumn();
    if ($old_img && strpos($old_img, 'assets/images/busker_uploads/') === 0) {
        $old_path = __DIR__ . '/../' . $old_img;
        if (file_exists($old_path)) {
            unlink($old_path);
        }
    }
    $stmt = $conn->prepare("UPDATE busker SET profile_image = NULL WHERE busker_id = ?");
    $stmt->execute([$busker_id]);
    header('Location: profile.php?success=profile_remove');
    exit();
}
// Handle background image removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_background_image'])) {
    // Delete old background image file
    $stmt = $conn->prepare("SELECT background_image FROM busker WHERE busker_id = ?");
    $stmt->execute([$busker_id]);
    $old_img = $stmt->fetchColumn();
    if ($old_img && strpos($old_img, 'assets/images/busker_uploads/') === 0) {
        $old_path = __DIR__ . '/../' . $old_img;
        if (file_exists($old_path)) {
            unlink($old_path);
        }
    }
    $stmt = $conn->prepare("UPDATE busker SET background_image = NULL WHERE busker_id = ?");
    $stmt->execute([$busker_id]);
    header('Location: profile.php?success=background_remove');
    exit();
}
// Fetch busker data
try {
    $stmt = $conn->prepare("SELECT band_name, name, contact_number, address, birthday, has_equipment, email, profile_image, background_image FROM busker WHERE busker_id = ?");
    $stmt->execute([$busker_id]);
    $busker_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $conn->prepare("SELECT g.genre_id, g.name FROM busker_genre bg JOIN genre g ON bg.genre_id = g.genre_id WHERE bg.busker_id = ?");
    $stmt->execute([$busker_id]);
    $busker_genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $busker_genre_ids = array_column($busker_genres, 'genre_id');
    $stmt = $conn->prepare("SELECT equipment_id, equipment_name, quantity, eq_condition FROM busker_equipment WHERE busker_id = ?");
    $stmt->execute([$busker_id]);
    $busker_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $conn->query("SELECT genre_id, name FROM genre ORDER BY name");
    $all_genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to load profile data.';
}
function getImage($img, $default) {
    if ($img && file_exists(__DIR__ . '/../' . $img)) {
        return '/tbcph/' . ltrim($img, '/');
    }
    return $default;
}
$default_profile = '/tbcph/assets/images/placeholder.jpg';
$default_bg = '/tbcph/assets/images/backgrounds/default_cover.jpg';

// Show messages based on query string
if (isset($_GET['success'])) {
    $map = [
        'profile_image' => 'Profile image updated!',
        'background_image' => 'Background image updated!',
        'profile_update' => 'Profile updated successfully!',
        'equipment_add' => 'Equipment added successfully!',
        'equipment_delete' => 'Equipment deleted successfully!',
        'profile_remove' => 'Profile image removed!',
        'background_remove' => 'Background image removed!'
    ];
    $success = $map[$_GET['success']] ?? 'Action successful!';
}
if (isset($_GET['error'])) {
    $map = [
        'upload_fail' => 'Failed to upload image.',
        'upload_error' => 'Error uploading image.',
        'profile_update' => 'Error updating profile.',
        'equipment_add' => 'Error adding equipment.',
        'equipment_delete' => 'Error deleting equipment.'
    ];
    $error = $map[$_GET['error']] ?? 'An error occurred.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Busker Profile</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .cover-photo {
            width: 100%;
            height: 320px;
            background: #eee;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .profile-pic {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            border: 6px solid #fff;
            object-fit: cover;
            position: absolute;
            left: 40px;
            bottom: -80px;
            background: #fff;
        }
        .upload-btn {
            position: absolute;
            right: 20px;
            bottom: 20px;
            background: rgba(0,0,0,0.5);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 6px 14px;
            cursor: pointer;
            z-index: 2;
        }
        .profile-main {
            margin-top: 100px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            padding: 32px;
        }
        .profile-info h2 {
            margin-bottom: 0.5rem;
        }
        .profile-info .badge {
            margin-bottom: 6px;
        }
        .equipment-list-section ul {
            list-style: none;
            padding: 0;
        }
        .equipment-list-section li {
            background-color: #e9ecef;
            border: 1px solid #dee2e6;
            padding: 8px 15px;
            margin-bottom: 8px;
            border-radius: .25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .equipment-list-section .remove-equipment-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0 5px;
        }
        .equipment-list-section .remove-equipment-btn:hover {
            color: #c82333;
        }
        @media (max-width: 700px) {
            .cover-photo { height: 180px; }
            .profile-pic { width: 100px; height: 100px; left: 16px; bottom: -50px; border-width: 4px; }
            .profile-main { margin-top: 60px; padding: 16px; }
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="cover-photo" style="background-image: url('<?php echo getImage($busker_data['background_image'] ?? '', $default_bg); ?>'); position:relative;">
    <label id="coverPhotoTrigger" style="position:absolute;top:0;left:0;width:100%;height:100%;cursor:pointer;z-index:2;">
        <span style="display:block;width:100%;height:100%;"></span>
    </label>
    <img src="<?php echo getImage($busker_data['profile_image'] ?? '', $default_profile); ?>" class="profile-pic" alt="Profile" id="profilePicView" style="cursor:pointer;">
</div>
<div class="profile-main">
    <div class="profile-header" style="border-bottom:1px solid #eee; margin-bottom:24px; display: flex; justify-content: space-between; align-items: center;">
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($busker_data['band_name'] ?: $busker_data['name']); ?></h2>
            <div class="mb-2 text-muted">@<?php echo htmlspecialchars($busker_data['name']); ?></div>
            <div class="genre-badges mb-2">
                <?php foreach ($busker_genres as $g): ?>
                    <span class="badge badge-info"><?php echo htmlspecialchars($g['name']); ?></span>
                <?php endforeach; ?>
            </div>
            <div class="mb-2"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($busker_data['email']); ?></div>
            <div class="mb-2"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($busker_data['contact_number']); ?></div>
            <div class="mb-2"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($busker_data['address']); ?></div>
            <div class="mb-2"><i class="fas fa-birthday-cake"></i> <?php echo htmlspecialchars($busker_data['birthday']); ?></div>
            <div class="mb-2"><i class="fas fa-tools"></i> Has Equipment: <?php echo $busker_data['has_equipment'] ? 'Yes' : 'No'; ?></div>
        </div>
        <button class="btn btn-primary btn-lg" data-toggle="modal" data-target="#editProfileModal"><i class="fas fa-edit"></i> Edit Profile</button>
    </div>
    <div class="mb-4">
        <h4>Equipment</h4>
        <div class="equipment-list-section">
            <?php if (!empty($busker_equipment)): ?>
                <ul>
                    <?php foreach ($busker_equipment as $eq): ?>
                        <li>
                            <?php echo htmlspecialchars($eq['equipment_name'] ?? '') . ' (' . htmlspecialchars($eq['quantity'] ?? '') . ', ' . htmlspecialchars($eq['eq_condition'] ?? '') . ')'; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No equipment listed.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-group">
                        <label for="email">Email (Cannot be changed)</label>
                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($busker_data['email']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="band_name">Band Name (Optional)</label>
                        <input type="text" class="form-control" id="band_name" name="band_name" value="<?php echo htmlspecialchars($busker_data['band_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($busker_data['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($busker_data['contact_number']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($busker_data['address']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="birthday">Birthday</label>
                        <input type="date" class="form-control" id="birthday" name="birthday" value="<?php echo htmlspecialchars($busker_data['birthday']); ?>">
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="has_equipment" name="has_equipment" value="1" <?php echo $busker_data['has_equipment'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="has_equipment">I have my own equipment</label>
                    </div>
                    <div class="form-group">
                        <label>Genres</label>
                        <div class="genre-checkbox-group">
                            <?php foreach ($all_genres as $genre): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="genre_<?php echo $genre['genre_id']; ?>" name="genres[]" value="<?php echo $genre['genre_id']; ?>"
                                        <?php echo in_array($genre['genre_id'], $busker_genre_ids) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="genre_<?php echo $genre['genre_id']; ?>"><?php echo htmlspecialchars($genre['name']); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <hr>
                    <h5>Equipment Management</h5>
                    <div class="equipment-list-section">
                        <?php if (!empty($busker_equipment)): ?>
                            <ul>
                                <?php foreach ($busker_equipment as $eq): ?>
                                    <li>
                                        <?php echo htmlspecialchars($eq['equipment_name'] ?? '') . ' (' . htmlspecialchars($eq['quantity'] ?? '') . ', ' . htmlspecialchars($eq['eq_condition'] ?? '') . ')'; ?>
                                        <form action="" method="POST" style="display:inline;">
                                            <input type="hidden" name="delete_equipment" value="1">
                                            <input type="hidden" name="equipment_id" value="<?php echo $eq['equipment_id']; ?>">
                                            <button type="submit" class="remove-equipment-btn" onclick="return confirm('Are you sure you want to delete this equipment?');"><i class="fas fa-times"></i></button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No equipment listed.</p>
                        <?php endif; ?>
                        <form action="" method="POST" class="mt-3">
                            <input type="hidden" name="add_equipment" value="1">
                            <div class="form-row">
                                <div class="col">
                                    <input type="text" class="form-control" name="equipment_name" placeholder="Equipment Name" required>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control" name="quantity" placeholder="Quantity">
                                </div>
                                <div class="col">
                                    <select class="form-control" name="eq_condition">
                                        <option value="Excellent">Excellent</option>
                                        <option value="Good">Good</option>
                                        <option value="Fair">Fair</option>
                                        <option value="Poor">Poor</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-success">Add</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Add this after the main content -->
<div class="modal fade" id="profilePicModal" tabindex="-1" role="dialog" aria-labelledby="profilePicModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content" style="background:#fff; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,0.2);">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="profilePicModalLabel">Profile Picture</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <img src="<?php echo getImage($busker_data['profile_image'] ?? '', $default_profile); ?>" alt="Profile Full" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.15); margin-bottom: 20px;">
        <form action="" method="POST" enctype="multipart/form-data" style="margin-bottom: 16px;">
          <input type="hidden" name="upload_image" value="1">
          <input type="hidden" name="upload_type" value="profile">
          <input type="file" name="image_file" accept="image/*" style="display:none;" onchange="this.form.submit()" id="profileUploadModal">
          <label for="profileUploadModal" class="btn btn-primary btn-block mb-2"><i class="fas fa-upload"></i> Upload/Replace Profile Picture</label>
        </form>
        <form action="" method="POST">
          <input type="hidden" name="remove_profile_image" value="1">
          <button type="submit" class="btn btn-danger btn-block"><i class="fas fa-trash"></i> Remove Profile Picture</button>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- Background Image Modal -->
<div class="modal fade" id="bgPicModal" tabindex="-1" role="dialog" aria-labelledby="bgPicModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content" style="background:#fff; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,0.2);">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="bgPicModalLabel">Background Image</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <img src="<?php echo getImage($busker_data['background_image'] ?? '', $default_bg); ?>" alt="Background Full" style="max-width: 100%; max-height: 50vh; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.15); margin-bottom: 20px;">
        <form action="" method="POST" enctype="multipart/form-data" style="margin-bottom: 16px;">
          <input type="hidden" name="upload_image" value="1">
          <input type="hidden" name="upload_type" value="background">
          <input type="file" name="image_file" accept="image/*" style="display:none;" onchange="this.form.submit()" id="bgUploadModal">
          <label for="bgUploadModal" class="btn btn-primary btn-block mb-2"><i class="fas fa-upload"></i> Upload/Replace Background Image</label>
        </form>
        <form action="" method="POST">
          <input type="hidden" name="remove_background_image" value="1">
          <button type="submit" class="btn btn-danger btn-block"><i class="fas fa-trash"></i> Remove Background Image</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
$(function() {
  $('#profilePicView').on('click', function() {
    $('#profilePicModal').modal('show');
  });
  $('#coverPhotoTrigger').on('click', function() {
    $('#bgPicModal').modal('show');
  });
});
</script>
</body>
</html>  
