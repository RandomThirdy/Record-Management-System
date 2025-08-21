
CREATE DEFINER=`root`@`localhost` PROCEDURE `InitializeAcademicPeriod` (IN `p_academic_year` YEAR, IN `p_semester` VARCHAR(20))   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_user_id INT;
    DECLARE v_doc_type VARCHAR(100);
    DECLARE user_cursor CURSOR FOR 
        SELECT id FROM users WHERE is_approved = 1 AND role = 'user';
    DECLARE doc_cursor CURSOR FOR
        SELECT DISTINCT document_type FROM document_requirements 
        WHERE academic_year = p_academic_year AND semester = p_semester AND is_required = 1;  
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    OPEN user_cursor;
    user_loop: LOOP
        FETCH user_cursor INTO v_user_id;
        IF done THEN
            LEAVE user_loop;
        END IF;
        
        SET done = FALSE;
        OPEN doc_cursor;
        doc_loop: LOOP
            FETCH doc_cursor INTO v_doc_type;
            IF done THEN
                LEAVE doc_loop;
            END IF;
            INSERT IGNORE INTO faculty_document_submissions 
            (faculty_id, document_type, semester, academic_year, submitted_by, submitted_at)
            VALUES (v_user_id, v_doc_type, p_semester, p_academic_year, v_user_id, NOW());
        END LOOP;
        CLOSE doc_cursor;
        SET done = FALSE;
    END LOOP;
    CLOSE user_cursor;
    SELECT CONCAT('Academic period ', p_academic_year, ' - ', p_semester, ' initialized successfully') as result;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cleanup_expired_shares` ()   BEGIN
    UPDATE file_shares 
    SET is_active = 0 
    WHERE expires_at < NOW() AND is_active = 1;
    SELECT ROW_COUNT() as expired_shares_count;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_document_workflow` (IN `request_id` INT, IN `document_type` VARCHAR(50))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    CASE document_type
        WHEN 'certificate' THEN
            INSERT INTO document_workflows (request_id, step_number, step_name, step_description, expected_duration_hours) VALUES
            (request_id, 1, 'Initial Review', 'Review request and verify requirements', 24),
            (request_id, 2, 'Document Preparation', 'Prepare certificate document', 48),
            (request_id, 3, 'Approval', 'Get necessary approvals and signatures', 24),
            (request_id, 4, 'Final Review', 'Final quality check and review', 12),
            (request_id, 5, 'Completion', 'Document ready for pickup/delivery', 6);
            
        WHEN 'clearance' THEN
            INSERT INTO document_workflows (request_id, step_number, step_name, step_description, expected_duration_hours) VALUES
            (request_id, 1, 'Request Validation', 'Validate clearance request', 12),
            (request_id, 2, 'Background Check', 'Perform necessary background verification', 72),
            (request_id, 3, 'Department Clearance', 'Get clearance from relevant departments', 48),
            (request_id, 4, 'Final Approval', 'Final approval and document generation', 24),
            (request_id, 5, 'Release', 'Document ready for release', 6);
            
        WHEN 'permit' THEN
            INSERT INTO document_workflows (request_id, step_number, step_name, step_description, expected_duration_hours) VALUES
            (request_id, 1, 'Application Review', 'Review permit application', 24),
            (request_id, 2, 'Requirements Check', 'Verify all requirements are met', 48),
            (request_id, 3, 'Site Inspection', 'Conduct necessary inspections if required', 72),
            (request_id, 4, 'Approval Process', 'Process approval through relevant authorities', 48),
            (request_id, 5, 'Permit Issuance', 'Issue permit document', 12);
            
        ELSE 
            INSERT INTO document_workflows (request_id, step_number, step_name, step_description, expected_duration_hours) VALUES
            (request_id, 1, 'Initial Review', 'Review request and requirements', 24),
            (request_id, 2, 'Processing', 'Process the document request', 48),
            (request_id, 3, 'Review and Approval', 'Review and approve the document', 24),
            (request_id, 4, 'Finalization', 'Finalize and prepare document', 12);
    END CASE;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_document_stats` (IN `user_id` INT, IN `is_admin` BOOLEAN)   BEGIN
    IF is_admin THEN
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            AVG(CASE WHEN status = 'completed' AND actual_completion IS NOT NULL 
                THEN DATEDIFF(actual_completion, created_at) ELSE NULL END) as avg_completion_days
        FROM document_requests 
        WHERE is_deleted = 0;
    ELSE
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            AVG(CASE WHEN status = 'completed' AND actual_completion IS NOT NULL 
                THEN DATEDIFF(actual_completion, created_at) ELSE NULL END) as avg_completion_days
        FROM document_requests 
        WHERE user_id = user_id AND is_deleted = 0;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_folder_stats` (IN `folder_id` INT)   BEGIN
    SELECT 
        COUNT(f.id) as file_count,
        COALESCE(SUM(f.file_size), 0) as total_size,
        COUNT(DISTINCT f.file_type) as file_types_count,
        MAX(f.uploaded_at) as last_upload
    FROM files f
    WHERE f.folder_id = folder_id AND f.is_deleted = 0;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_user_activity` (IN `user_id` INT, IN `days_back` INT)   BEGIN
    SELECT 
        action,
        resource_type,
        COUNT(*) as action_count,
        MAX(created_at) as last_action
    FROM activity_logs
    WHERE user_id = user_id 
    AND created_at >= DATE_SUB(NOW(), INTERVAL days_back DAY)
    GROUP BY action, resource_type
    ORDER BY action_count DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_folder_stats` (IN `folder_id` INT)   BEGIN
    UPDATE folders f
    SET 
        file_count = (
            SELECT COUNT(*) 
            FROM files 
            WHERE folder_id = f.id AND is_deleted = 0
        ),
        folder_size = (
            SELECT COALESCE(SUM(file_size), 0) 
            FROM files 
            WHERE folder_id = f.id AND is_deleted = 0
        )
    WHERE f.id = folder_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_workflow_step` (IN `workflow_id` INT, IN `new_status` VARCHAR(20), IN `user_id` INT, IN `step_notes` TEXT)   BEGIN
    DECLARE request_id INT;
    DECLARE step_name VARCHAR(100);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    UPDATE document_workflows 
    SET 
        status = new_status,
        completed_at = CASE WHEN new_status = 'completed' THEN NOW() ELSE completed_at END,
        started_at = CASE WHEN new_status = 'in_progress' AND started_at IS NULL THEN NOW() ELSE started_at END,
        actual_duration_minutes = CASE 
            WHEN new_status = 'completed' AND started_at IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, started_at, NOW()) 
            ELSE actual_duration_minutes 
        END,
        notes = COALESCE(step_notes, notes),
        updated_at = NOW()
    WHERE id = workflow_id;

    SELECT dw.request_id, dw.step_name INTO request_id, step_name
    FROM document_workflows dw WHERE dw.id = workflow_id;

    INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, metadata)
    VALUES (user_id, 'update_workflow_step', 'document', request_id, 
            CONCAT('Updated workflow step "', step_name, '" to ', new_status),
            JSON_OBJECT('workflow_id', workflow_id, 'new_status', new_status, 'step_name', step_name));

    IF new_status = 'completed' THEN
        UPDATE document_workflows 
        SET status = 'pending', updated_at = NOW()
        WHERE request_id = request_id 
        AND step_number = (SELECT step_number + 1 FROM document_workflows WHERE id = workflow_id)
        AND status = 'pending';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_generate_share_token` () RETURNS VARCHAR(255) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE token VARCHAR(255);
    DECLARE token_exists INT DEFAULT 1;
    
    WHILE token_exists > 0 DO
        SET token = CONCAT('share_', MD5(CONCAT(NOW(), RAND())));
        SELECT COUNT(*) INTO token_exists FROM file_shares WHERE share_token = token;
    END WHILE;
    
    RETURN token;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_generate_tracking_code` () RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE next_number INT;
    DECLARE tracking_code VARCHAR(20);
    DECLARE current_month VARCHAR(6);
    
    SET current_month = DATE_FORMAT(NOW(), '%Y%m');

    SELECT COALESCE(MAX(CAST(SUBSTRING(tracking_code, -4) AS UNSIGNED)), 0) + 1 
    INTO next_number
    FROM document_requests 
    WHERE tracking_code LIKE CONCAT('DOC-', current_month, '-%');
    
    SET tracking_code = CONCAT('DOC-', current_month, '-', LPAD(next_number, 4, '0'));
    
    RETURN tracking_code;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `GetCurrentSemester` () RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE current_month INT;
    SET current_month = MONTH(CURDATE());

    IF current_month >= 6 AND current_month <= 11 THEN
        RETURN '1st Semester';
    ELSE
        RETURN '2nd Semester';
    END IF;
END$$

DELIMITER ;

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `resource_type` enum('file','folder','user','announcement','department','system') NOT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `announcement_type` enum('general','department','urgent','maintenance') DEFAULT 'general',
  `target_departments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_departments`)),
  `target_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_roles`)),
  `is_published` tinyint(1) DEFAULT 0,
  `published_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `view_count` int(11) DEFAULT 0,
  `is_pinned` tinyint(1) DEFAULT 0,
  `allow_comments` tinyint(1) DEFAULT 1,
  `send_email` tinyint(1) DEFAULT 0,
  `email_sent` tinyint(1) DEFAULT 0,
  `email_sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `announcement_views` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `viewed_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE TRIGGER `tr_announcement_view_insert` AFTER INSERT ON `announcement_views` FOR EACH ROW BEGIN
    UPDATE announcements 
    SET view_count = (
        SELECT COUNT(*) FROM announcement_views 
        WHERE announcement_id = NEW.announcement_id
    )
    WHERE id = NEW.announcement_id;
END
$$
DELIMITER ;

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_code` varchar(10) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_of_department` varchar(100) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `document_attachments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_type` enum('requirement','supporting','output','other') DEFAULT 'supporting',
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `document_comments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `comment_type` enum('general','status_update','requirement','internal') DEFAULT 'general',
  `parent_comment_id` int(11) DEFAULT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `document_files` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `academic_year` year(4) DEFAULT NULL,
  `semester_period` varchar(20) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `mime_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `document_notifications` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_type` enum('status_change','assignment','deadline','comment','completion') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `action_url` varchar(500) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL,
  `tracking_code` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` enum('certificate','clearance','permit','report','form','other') NOT NULL DEFAULT 'other',
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('pending','in_progress','under_review','completed','rejected','cancelled') DEFAULT 'pending',
  `target_department` int(11) DEFAULT NULL,
  `expected_completion` date DEFAULT NULL,
  `actual_completion` datetime DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE TRIGGER `tr_document_request_insert` BEFORE INSERT ON `document_requests` FOR EACH ROW BEGIN
    DECLARE next_id INT;
    DECLARE tracking_code VARCHAR(20);

    SELECT AUTO_INCREMENT INTO next_id FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requests';
    
    SET tracking_code = CONCAT('DOC-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD(next_id, 4, '0'));
    SET NEW.tracking_code = tracking_code;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_document_request_log` AFTER INSERT ON `document_requests` FOR EACH ROW BEGIN
    INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, metadata)
    VALUES (NEW.user_id, 'create_document_request', 'document', NEW.id, 
            CONCAT('Created document request: ', NEW.title, ' (', NEW.tracking_code, ')'),
            JSON_OBJECT('tracking_code', NEW.tracking_code, 'document_type', NEW.document_type, 'priority', NEW.priority));
END
$$
DELIMITER ;

CREATE TABLE `document_requirements` (
  `id` int(11) NOT NULL,
  `academic_year` year(4) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `document_type` varchar(100) NOT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `deadline_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `document_status_history` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `status` enum('pending','in_progress','under_review','completed','rejected','cancelled') NOT NULL,
  `changed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE TRIGGER `tr_document_status_log` AFTER INSERT ON `document_status_history` FOR EACH ROW BEGIN
    INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, metadata)
    VALUES (NEW.changed_by, 'update_document_status', 'document', NEW.request_id, 
            CONCAT('Updated document status to: ', NEW.status),
            JSON_OBJECT('new_status', NEW.status, 'notes', NEW.notes));
END
$$
DELIMITER ;

CREATE TABLE `document_submission_history` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `status` enum('submitted','not_submitted','reminder_sent','note_added') NOT NULL,
  `note` text DEFAULT NULL,
  `reminder_sent` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `document_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `document_type` enum('certificate','clearance','permit','report','form','other') NOT NULL,
  `template_description` text DEFAULT NULL,
  `required_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_fields`)),
  `workflow_steps` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`workflow_steps`)),
  `estimated_completion_hours` int(11) DEFAULT 24,
  `department_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `document_workflows` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `step_number` int(11) NOT NULL,
  `step_name` varchar(100) NOT NULL,
  `step_description` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_department` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','skipped','failed') DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `expected_duration_hours` int(11) DEFAULT NULL,
  `actual_duration_minutes` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `faculty_document_submissions` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `academic_year` year(4) NOT NULL DEFAULT year(curdate()),
  `submitted_by` int(11) NOT NULL,
  `submitted_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_extension` varchar(10) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `file_hash` varchar(64) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `last_downloaded` datetime DEFAULT NULL,
  `last_downloaded_by` int(11) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `public_token` varchar(255) DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `version` int(11) DEFAULT 1,
  `parent_file_id` int(11) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `description` text DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `is_favorite` tinyint(1) DEFAULT 0,
  `expiry_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `files` (`id`, `file_name`, `original_name`, `file_path`, `file_size`, `file_type`, `mime_type`, `file_extension`, `uploaded_by`, `folder_id`, `uploaded_at`, `updated_at`, `is_deleted`, `deleted_at`, `deleted_by`, `file_hash`, `download_count`, `last_downloaded`, `last_downloaded_by`, `is_public`, `public_token`, `permissions`, `version`, `parent_file_id`, `tags`, `description`, `thumbnail_path`, `is_favorite`, `expiry_date`) VALUES
(18, '1719775504_summary_form.docx', 'NAIC_QF_xxxx_Summary-of-Comments-and-Action-Taken-Form.docx', 'uploads/1719775504_summary_form.docx', 45678, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 28, 47, '2025-06-30 23:25:04', '2025-08-07 01:55:15', 0, NULL, NULL, 'abc123def456', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Quality assurance summary form', NULL, 0, NULL),
(19, '1719775992_users_backup.sql', 'users.sql', 'uploads/1719775992_users_backup.sql', 8945, 'database', 'application/sql', 'sql', 29, 47, '2025-06-30 23:33:12', '2025-08-07 01:55:15', 0, NULL, NULL, 'def456ghi789', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Database backup file', NULL, 0, NULL),
(20, '1719832532_contribution_doc.docx', 'WITH CONTRIBUTION NUMBER.docx', 'uploads/1719832532_contribution_doc.docx', 234567, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 30, 51, '2025-07-01 11:55:32', '2025-08-07 01:55:15', 0, NULL, NULL, 'ghi789jkl012', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Research contribution document', NULL, 0, NULL),
(21, '1719832561_admin_dashboard.php', 'admindashboard.php', 'uploads/1719832561_admin_dashboard.php', 15678, 'code', 'application/x-php', 'php', 30, 52, '2025-07-01 11:56:01', '2025-08-07 01:55:15', 0, NULL, NULL, 'jkl012mno345', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Admin dashboard source code', NULL, 0, NULL),
(22, '1719832585_authorization_letter.docx', 'AUTHORIZATION LETTER.docx', 'uploads/1719832585_authorization_letter.docx', 67890, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 28, 46, '2025-07-01 11:56:25', '2025-08-07 01:55:15', 0, NULL, NULL, 'mno345pqr678', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Official authorization letter', NULL, 0, NULL);

DELIMITER $$
CREATE TRIGGER `tr_file_insert_log` AFTER INSERT ON `files` FOR EACH ROW BEGIN
    INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, metadata)
    VALUES (NEW.uploaded_by, 'upload_file', 'file', NEW.id, 
            CONCAT('Uploaded file: ', NEW.original_name),
            JSON_OBJECT('file_size', NEW.file_size, 'file_type', NEW.file_type));
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_file_insert_update_folder` AFTER INSERT ON `files` FOR EACH ROW BEGIN
    CALL sp_update_folder_stats(NEW.folder_id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_file_update_folder_stats` AFTER UPDATE ON `files` FOR EACH ROW BEGIN
    IF OLD.is_deleted != NEW.is_deleted THEN
        CALL sp_update_folder_stats(NEW.folder_id);
    END IF;
END
$$
DELIMITER ;

CREATE TABLE `file_comments` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `file_shares` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `shared_by` int(11) NOT NULL,
  `shared_with` int(11) DEFAULT NULL,
  `share_token` varchar(255) NOT NULL,
  `share_type` enum('user','public','department') DEFAULT 'user',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `expires_at` datetime DEFAULT NULL,
  `password_protected` tinyint(1) DEFAULT 0,
  `share_password` varchar(255) DEFAULT NULL,
  `download_limit` int(11) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_accessed` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `folders` (
  `id` int(11) NOT NULL,
  `folder_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `parent_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `folder_path` varchar(500) DEFAULT NULL,
  `folder_level` int(11) DEFAULT 0,
  `is_public` tinyint(1) DEFAULT 0,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `folder_size` bigint(20) DEFAULT 0,
  `file_count` int(11) DEFAULT 0,
  `folder_color` varchar(7) DEFAULT '#667eea',
  `folder_icon` varchar(50) DEFAULT 'fa-folder'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `folder_permissions` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `permission_type` enum('read','write','admin') DEFAULT 'read',
  `granted_by` int(11) NOT NULL,
  `granted_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `folder_permissions` (`id`, `folder_id`, `user_id`, `department_id`, `role`, `permission_type`, `granted_by`, `granted_at`, `expires_at`, `is_active`) VALUES
(1, 48, 28, NULL, NULL, 'write', 27, '2025-08-07 01:55:16', NULL, 1),
(2, 49, 28, NULL, NULL, 'write', 27, '2025-08-07 01:55:16', NULL, 1),
(3, 48, 29, NULL, NULL, 'read', 27, '2025-08-07 01:55:16', NULL, 1),
(4, 49, 29, NULL, NULL, 'write', 27, '2025-08-07 01:55:16', NULL, 1),
(5, 50, 30, NULL, NULL, 'read', 27, '2025-08-07 01:55:16', NULL, 1),
(6, 51, 30, NULL, NULL, 'write', 27, '2025-08-07 01:55:16', NULL, 1);

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `action_url` varchar(500) DEFAULT NULL,
  `action_text` varchar(100) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `content_type` enum('text','image','file','link','mixed') DEFAULT 'text',
  `visibility` enum('public','department','private','custom') DEFAULT 'public',
  `target_departments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_departments`)),
  `target_users` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_users`)),
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `like_count` int(11) DEFAULT 0,
  `comment_count` int(11) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `share_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `post_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `like_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE TRIGGER `tr_post_comment_insert` AFTER INSERT ON `post_comments` FOR EACH ROW BEGIN
    UPDATE posts 
    SET comment_count = (
        SELECT COUNT(*) FROM post_comments 
        WHERE post_id = NEW.post_id AND is_deleted = 0
    )
    WHERE id = NEW.post_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_post_comment_update` AFTER UPDATE ON `post_comments` FOR EACH ROW BEGIN
    IF OLD.is_deleted != NEW.is_deleted THEN
        UPDATE posts 
        SET comment_count = (
            SELECT COUNT(*) FROM post_comments 
            WHERE post_id = NEW.post_id AND is_deleted = 0
        )
        WHERE id = NEW.post_id;
    END IF;
END
$$
DELIMITER ;

CREATE TABLE `post_likes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `comment_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `reaction_type` enum('like','love','laugh','angry','sad','wow') DEFAULT 'like',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE TRIGGER `tr_post_like_delete` AFTER DELETE ON `post_likes` FOR EACH ROW BEGIN
    IF OLD.post_id IS NOT NULL THEN
        UPDATE posts 
        SET like_count = (
            SELECT COUNT(*) FROM post_likes 
            WHERE post_id = OLD.post_id
        )
        WHERE id = OLD.post_id;
    ELSEIF OLD.comment_id IS NOT NULL THEN
        UPDATE post_comments 
        SET like_count = (
            SELECT COUNT(*) FROM post_likes 
            WHERE comment_id = OLD.comment_id
        )
        WHERE id = OLD.comment_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_post_like_insert` AFTER INSERT ON `post_likes` FOR EACH ROW BEGIN
    IF NEW.post_id IS NOT NULL THEN
        UPDATE posts 
        SET like_count = (
            SELECT COUNT(*) FROM post_likes 
            WHERE post_id = NEW.post_id
        )
        WHERE id = NEW.post_id;
    ELSEIF NEW.comment_id IS NOT NULL THEN
        UPDATE post_comments 
        SET like_count = (
            SELECT COUNT(*) FROM post_likes 
            WHERE comment_id = NEW.comment_id
        )
        WHERE id = NEW.comment_id;
    END IF;
END
$$
DELIMITER ;

CREATE TABLE `post_media` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `media_type` enum('image','file','link') NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `url` varchar(1000) DEFAULT NULL,
  `url_title` varchar(255) DEFAULT NULL,
  `url_description` text DEFAULT NULL,
  `url_image` varchar(500) DEFAULT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `post_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `comment_id` int(11) DEFAULT NULL,
  `triggered_by` int(11) NOT NULL,
  `notification_type` enum('new_post','post_comment','post_like','comment_like','comment_reply','post_mention','comment_mention') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `post_shares` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `share_type` enum('internal','external','copy_link') DEFAULT 'internal',
  `shared_with` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shared_with`)),
  `message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE TRIGGER `tr_post_share_insert` AFTER INSERT ON `post_shares` FOR EACH ROW BEGIN
    UPDATE posts 
    SET share_count = (
        SELECT COUNT(*) FROM post_shares 
        WHERE post_id = NEW.post_id
    )
    WHERE id = NEW.post_id;
END
$$
DELIMITER ;

CREATE TABLE `post_views` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `viewed_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE TRIGGER `tr_post_view_insert` AFTER INSERT ON `post_views` FOR EACH ROW BEGIN
    UPDATE posts 
    SET view_count = (
        SELECT COUNT(*) FROM post_views 
        WHERE post_id = NEW.post_id
    )
    WHERE id = NEW.post_id;
END
$$
DELIMITER ;

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user','super_admin') NOT NULL DEFAULT 'user',
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL,
  `mi` varchar(5) DEFAULT NULL,
  `surname` varchar(100) NOT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `is_restricted` tinyint(1) DEFAULT 0,
  `profile_image` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `account_locked_until` datetime DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `v_admin_submission_stats` (
`academic_year` year(4)
,`semester` varchar(50)
,`department_code` varchar(10)
,`department_name` varchar(100)
,`total_faculty` bigint(21)
,`submitted_docs` bigint(21)
,`required_doc_types` bigint(21)
,`total_required_submissions` bigint(41)
,`completion_percentage` decimal(26,2)
);

CREATE TABLE `v_announcements_detailed` (
`id` int(11)
,`title` varchar(255)
,`content` text
,`summary` varchar(500)
,`image_path` varchar(255)
,`priority` enum('low','normal','high','urgent')
,`announcement_type` enum('general','department','urgent','maintenance')
,`is_published` tinyint(1)
,`published_at` datetime
,`expires_at` datetime
,`view_count` int(11)
,`is_pinned` tinyint(1)
,`created_at` datetime
,`created_by_username` varchar(50)
,`creator_full_name` varchar(208)
);

CREATE TABLE `v_comments_detailed` (
`id` int(11)
,`post_id` int(11)
,`user_id` int(11)
,`parent_comment_id` int(11)
,`content` text
,`is_edited` tinyint(1)
,`edited_at` datetime
,`is_deleted` tinyint(1)
,`deleted_at` datetime
,`deleted_by` int(11)
,`created_at` datetime
,`updated_at` datetime
,`like_count` int(11)
,`username` varchar(50)
,`name` varchar(100)
,`mi` varchar(5)
,`surname` varchar(100)
,`commenter_full_name` varchar(208)
,`profile_image` varchar(255)
,`position` varchar(100)
,`department_code` varchar(10)
,`deleted_by_username` varchar(50)
);

CREATE TABLE `v_document_requests_detailed` (
`id` int(11)
,`tracking_code` varchar(20)
,`user_id` int(11)
,`document_type` enum('certificate','clearance','permit','report','form','other')
,`title` varchar(255)
,`description` text
,`priority` enum('low','normal','high','urgent')
,`status` enum('pending','in_progress','under_review','completed','rejected','cancelled')
,`target_department` int(11)
,`expected_completion` date
,`actual_completion` datetime
,`assigned_to` int(11)
,`metadata` longtext
,`created_at` datetime
,`updated_at` datetime
,`updated_by` int(11)
,`is_deleted` tinyint(1)
,`deleted_at` datetime
,`deleted_by` int(11)
,`requester_name` varchar(50)
,`requester_full_name` varchar(208)
,`requester_email` varchar(255)
,`requester_department` varchar(100)
,`target_dept_name` varchar(100)
,`target_dept_code` varchar(10)
,`assigned_to_name` varchar(208)
,`updated_by_name` varchar(208)
,`comment_count` bigint(21)
,`attachment_count` bigint(21)
);

CREATE TABLE `v_files_detailed` (
`id` int(11)
,`original_name` varchar(255)
,`file_name` varchar(255)
,`file_size` bigint(20)
,`file_type` varchar(100)
,`mime_type` varchar(100)
,`file_extension` varchar(10)
,`uploaded_at` datetime
,`download_count` int(11)
,`is_deleted` tinyint(1)
,`folder_name` varchar(100)
,`folder_path` varchar(500)
,`uploaded_by_username` varchar(50)
,`uploader_full_name` varchar(208)
,`folder_department` varchar(10)
);

CREATE TABLE `v_folders_hierarchy` (
`id` int(11)
,`folder_name` varchar(100)
,`folder_path` varchar(500)
,`folder_level` int(11)
,`is_public` tinyint(1)
,`folder_color` varchar(7)
,`folder_icon` varchar(50)
,`file_count` int(11)
,`folder_size` bigint(20)
,`created_at` datetime
,`parent_folder_name` varchar(100)
,`created_by_username` varchar(50)
,`creator_full_name` varchar(208)
,`department_code` varchar(10)
,`department_name` varchar(100)
);

CREATE TABLE `v_posts_detailed` (
`id` int(11)
,`user_id` int(11)
,`content` text
,`content_type` enum('text','image','file','link','mixed')
,`visibility` enum('public','department','private','custom')
,`target_departments` longtext
,`target_users` longtext
,`priority` enum('low','normal','high','urgent')
,`is_pinned` tinyint(1)
,`is_edited` tinyint(1)
,`edited_at` datetime
,`is_deleted` tinyint(1)
,`deleted_at` datetime
,`deleted_by` int(11)
,`created_at` datetime
,`updated_at` datetime
,`like_count` int(11)
,`comment_count` int(11)
,`view_count` int(11)
,`share_count` int(11)
,`username` varchar(50)
,`name` varchar(100)
,`mi` varchar(5)
,`surname` varchar(100)
,`author_full_name` varchar(208)
,`profile_image` varchar(255)
,`position` varchar(100)
,`department_code` varchar(10)
,`department_name` varchar(100)
,`deleted_by_username` varchar(50)
);

CREATE TABLE `v_submission_tracker` (
`user_id` int(11)
,`username` varchar(50)
,`name` varchar(100)
,`surname` varchar(100)
,`department_id` int(11)
,`department_name` varchar(100)
,`department_code` varchar(10)
,`academic_year` year(4)
,`semester` varchar(50)
,`document_type` varchar(100)
,`file_count` bigint(21)
,`latest_upload` datetime
,`first_upload` datetime
,`total_size` decimal(32,0)
,`submitted_at` datetime
,`submitted_by` int(11)
,`status` varchar(12)
);

CREATE TABLE `v_users_detailed` (
`id` int(11)
,`username` varchar(50)
,`email` varchar(255)
,`role` enum('admin','user','super_admin')
,`is_approved` tinyint(1)
,`name` varchar(100)
,`mi` varchar(5)
,`surname` varchar(100)
,`full_name` varchar(208)
,`employee_id` varchar(20)
,`position` varchar(100)
,`is_restricted` tinyint(1)
,`last_login` datetime
,`created_at` datetime
,`department_code` varchar(10)
,`department_name` varchar(100)
,`approved_by_username` varchar(50)
,`approved_at` datetime
);

DROP TABLE IF EXISTS `v_admin_submission_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_admin_submission_stats`  AS SELECT `st`.`academic_year` AS `academic_year`, `st`.`semester` AS `semester`, `st`.`department_code` AS `department_code`, `st`.`department_name` AS `department_name`, count(distinct `st`.`user_id`) AS `total_faculty`, count(distinct case when `st`.`status` = 'uploaded' then concat(`st`.`user_id`,'-',`st`.`document_type`) end) AS `submitted_docs`, count(distinct `st`.`document_type`) AS `required_doc_types`, count(distinct `st`.`user_id`) * count(distinct `st`.`document_type`) AS `total_required_submissions`, round(count(distinct case when `st`.`status` = 'uploaded' then concat(`st`.`user_id`,'-',`st`.`document_type`) end) / (count(distinct `st`.`user_id`) * count(distinct `st`.`document_type`)) * 100,2) AS `completion_percentage` FROM `v_submission_tracker` AS `st` WHERE `st`.`academic_year` is not null AND `st`.`semester` is not null GROUP BY `st`.`academic_year`, `st`.`semester`, `st`.`department_code`, `st`.`department_name` ORDER BY `st`.`academic_year` DESC, `st`.`semester` ASC, `st`.`department_name` ASC ;

DROP TABLE IF EXISTS `v_announcements_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_announcements_detailed`  AS SELECT `a`.`id` AS `id`, `a`.`title` AS `title`, `a`.`content` AS `content`, `a`.`summary` AS `summary`, `a`.`image_path` AS `image_path`, `a`.`priority` AS `priority`, `a`.`announcement_type` AS `announcement_type`, `a`.`is_published` AS `is_published`, `a`.`published_at` AS `published_at`, `a`.`expires_at` AS `expires_at`, `a`.`view_count` AS `view_count`, `a`.`is_pinned` AS `is_pinned`, `a`.`created_at` AS `created_at`, `creator`.`username` AS `created_by_username`, concat(`creator`.`name`,' ',ifnull(concat(`creator`.`mi`,'. '),''),`creator`.`surname`) AS `creator_full_name` FROM (`announcements` `a` left join `users` `creator` on(`a`.`created_by` = `creator`.`id`)) WHERE `a`.`is_deleted` = 0 ;

DROP TABLE IF EXISTS `v_comments_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_comments_detailed`  AS SELECT `c`.`id` AS `id`, `c`.`post_id` AS `post_id`, `c`.`user_id` AS `user_id`, `c`.`parent_comment_id` AS `parent_comment_id`, `c`.`content` AS `content`, `c`.`is_edited` AS `is_edited`, `c`.`edited_at` AS `edited_at`, `c`.`is_deleted` AS `is_deleted`, `c`.`deleted_at` AS `deleted_at`, `c`.`deleted_by` AS `deleted_by`, `c`.`created_at` AS `created_at`, `c`.`updated_at` AS `updated_at`, `c`.`like_count` AS `like_count`, `u`.`username` AS `username`, `u`.`name` AS `name`, `u`.`mi` AS `mi`, `u`.`surname` AS `surname`, concat(`u`.`name`,' ',ifnull(concat(`u`.`mi`,'. '),''),`u`.`surname`) AS `commenter_full_name`, `u`.`profile_image` AS `profile_image`, `u`.`position` AS `position`, `d`.`department_code` AS `department_code`, `deleter`.`username` AS `deleted_by_username` FROM (((`post_comments` `c` left join `users` `u` on(`c`.`user_id` = `u`.`id`)) left join `departments` `d` on(`u`.`department_id` = `d`.`id`)) left join `users` `deleter` on(`c`.`deleted_by` = `deleter`.`id`)) ;

DROP TABLE IF EXISTS `v_document_requests_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_document_requests_detailed`  AS SELECT `dr`.`id` AS `id`, `dr`.`tracking_code` AS `tracking_code`, `dr`.`user_id` AS `user_id`, `dr`.`document_type` AS `document_type`, `dr`.`title` AS `title`, `dr`.`description` AS `description`, `dr`.`priority` AS `priority`, `dr`.`status` AS `status`, `dr`.`target_department` AS `target_department`, `dr`.`expected_completion` AS `expected_completion`, `dr`.`actual_completion` AS `actual_completion`, `dr`.`assigned_to` AS `assigned_to`, `dr`.`metadata` AS `metadata`, `dr`.`created_at` AS `created_at`, `dr`.`updated_at` AS `updated_at`, `dr`.`updated_by` AS `updated_by`, `dr`.`is_deleted` AS `is_deleted`, `dr`.`deleted_at` AS `deleted_at`, `dr`.`deleted_by` AS `deleted_by`, `u`.`username` AS `requester_name`, concat(`u`.`name`,' ',ifnull(concat(`u`.`mi`,'. '),''),`u`.`surname`) AS `requester_full_name`, `u`.`email` AS `requester_email`, `ud`.`department_name` AS `requester_department`, `td`.`department_name` AS `target_dept_name`, `td`.`department_code` AS `target_dept_code`, concat(`assigned`.`name`,' ',ifnull(concat(`assigned`.`mi`,'. '),''),`assigned`.`surname`) AS `assigned_to_name`, concat(`updater`.`name`,' ',ifnull(concat(`updater`.`mi`,'. '),''),`updater`.`surname`) AS `updated_by_name`, (select count(0) from `document_comments` where `document_comments`.`request_id` = `dr`.`id` and `document_comments`.`is_deleted` = 0) AS `comment_count`, (select count(0) from `document_attachments` where `document_attachments`.`request_id` = `dr`.`id` and `document_attachments`.`is_deleted` = 0) AS `attachment_count` FROM (((((`document_requests` `dr` left join `users` `u` on(`dr`.`user_id` = `u`.`id`)) left join `departments` `ud` on(`u`.`department_id` = `ud`.`id`)) left join `departments` `td` on(`dr`.`target_department` = `td`.`id`)) left join `users` `assigned` on(`dr`.`assigned_to` = `assigned`.`id`)) left join `users` `updater` on(`dr`.`updated_by` = `updater`.`id`)) WHERE `dr`.`is_deleted` = 0 ;

DROP TABLE IF EXISTS `v_files_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_files_detailed`  AS SELECT `f`.`id` AS `id`, `f`.`original_name` AS `original_name`, `f`.`file_name` AS `file_name`, `f`.`file_size` AS `file_size`, `f`.`file_type` AS `file_type`, `f`.`mime_type` AS `mime_type`, `f`.`file_extension` AS `file_extension`, `f`.`uploaded_at` AS `uploaded_at`, `f`.`download_count` AS `download_count`, `f`.`is_deleted` AS `is_deleted`, `folder`.`folder_name` AS `folder_name`, `folder`.`folder_path` AS `folder_path`, `uploader`.`username` AS `uploaded_by_username`, concat(`uploader`.`name`,' ',ifnull(concat(`uploader`.`mi`,'. '),''),`uploader`.`surname`) AS `uploader_full_name`, `dept`.`department_code` AS `folder_department` FROM (((`files` `f` left join `folders` `folder` on(`f`.`folder_id` = `folder`.`id`)) left join `users` `uploader` on(`f`.`uploaded_by` = `uploader`.`id`)) left join `departments` `dept` on(`folder`.`department_id` = `dept`.`id`)) ;

DROP TABLE IF EXISTS `v_folders_hierarchy`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_folders_hierarchy`  AS SELECT `f`.`id` AS `id`, `f`.`folder_name` AS `folder_name`, `f`.`folder_path` AS `folder_path`, `f`.`folder_level` AS `folder_level`, `f`.`is_public` AS `is_public`, `f`.`folder_color` AS `folder_color`, `f`.`folder_icon` AS `folder_icon`, `f`.`file_count` AS `file_count`, `f`.`folder_size` AS `folder_size`, `f`.`created_at` AS `created_at`, `parent`.`folder_name` AS `parent_folder_name`, `creator`.`username` AS `created_by_username`, concat(`creator`.`name`,' ',ifnull(concat(`creator`.`mi`,'. '),''),`creator`.`surname`) AS `creator_full_name`, `dept`.`department_code` AS `department_code`, `dept`.`department_name` AS `department_name` FROM (((`folders` `f` left join `folders` `parent` on(`f`.`parent_id` = `parent`.`id`)) left join `users` `creator` on(`f`.`created_by` = `creator`.`id`)) left join `departments` `dept` on(`f`.`department_id` = `dept`.`id`)) WHERE `f`.`is_deleted` = 0 ;

DROP TABLE IF EXISTS `v_posts_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_posts_detailed`  AS SELECT `p`.`id` AS `id`, `p`.`user_id` AS `user_id`, `p`.`content` AS `content`, `p`.`content_type` AS `content_type`, `p`.`visibility` AS `visibility`, `p`.`target_departments` AS `target_departments`, `p`.`target_users` AS `target_users`, `p`.`priority` AS `priority`, `p`.`is_pinned` AS `is_pinned`, `p`.`is_edited` AS `is_edited`, `p`.`edited_at` AS `edited_at`, `p`.`is_deleted` AS `is_deleted`, `p`.`deleted_at` AS `deleted_at`, `p`.`deleted_by` AS `deleted_by`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at`, `p`.`like_count` AS `like_count`, `p`.`comment_count` AS `comment_count`, `p`.`view_count` AS `view_count`, `p`.`share_count` AS `share_count`, `u`.`username` AS `username`, `u`.`name` AS `name`, `u`.`mi` AS `mi`, `u`.`surname` AS `surname`, concat(`u`.`name`,' ',ifnull(concat(`u`.`mi`,'. '),''),`u`.`surname`) AS `author_full_name`, `u`.`profile_image` AS `profile_image`, `u`.`position` AS `position`, `d`.`department_code` AS `department_code`, `d`.`department_name` AS `department_name`, `deleter`.`username` AS `deleted_by_username` FROM (((`posts` `p` left join `users` `u` on(`p`.`user_id` = `u`.`id`)) left join `departments` `d` on(`u`.`department_id` = `d`.`id`)) left join `users` `deleter` on(`p`.`deleted_by` = `deleter`.`id`)) ;

DROP TABLE IF EXISTS `v_submission_tracker`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_submission_tracker`  AS SELECT `u`.`id` AS `user_id`, `u`.`username` AS `username`, `u`.`name` AS `name`, `u`.`surname` AS `surname`, `u`.`department_id` AS `department_id`, `d`.`department_name` AS `department_name`, `d`.`department_code` AS `department_code`, `fds`.`academic_year` AS `academic_year`, `fds`.`semester` AS `semester`, `fds`.`document_type` AS `document_type`, count(`df`.`id`) AS `file_count`, max(`df`.`uploaded_at`) AS `latest_upload`, min(`df`.`uploaded_at`) AS `first_upload`, sum(`df`.`file_size`) AS `total_size`, `fds`.`submitted_at` AS `submitted_at`, `fds`.`submitted_by` AS `submitted_by`, CASE WHEN count(`df`.`id`) > 0 THEN 'uploaded' ELSE 'not_uploaded' END AS `status` FROM (((`users` `u` left join `departments` `d` on(`u`.`department_id` = `d`.`id`)) left join `faculty_document_submissions` `fds` on(`u`.`id` = `fds`.`faculty_id`)) left join `document_files` `df` on(`fds`.`id` = `df`.`submission_id` and `df`.`file_type` = `fds`.`document_type`)) WHERE `u`.`role` = 'user' AND `u`.`is_approved` = 1 GROUP BY `u`.`id`, `fds`.`academic_year`, `fds`.`semester`, `fds`.`document_type` ;

DROP TABLE IF EXISTS `v_users_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_users_detailed`  AS SELECT `u`.`id` AS `id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`role` AS `role`, `u`.`is_approved` AS `is_approved`, `u`.`name` AS `name`, `u`.`mi` AS `mi`, `u`.`surname` AS `surname`, concat(`u`.`name`,' ',ifnull(concat(`u`.`mi`,'. '),''),`u`.`surname`) AS `full_name`, `u`.`employee_id` AS `employee_id`, `u`.`position` AS `position`, `u`.`is_restricted` AS `is_restricted`, `u`.`last_login` AS `last_login`, `u`.`created_at` AS `created_at`, `d`.`department_code` AS `department_code`, `d`.`department_name` AS `department_name`, `approver`.`username` AS `approved_by_username`, `u`.`approved_at` AS `approved_at` FROM ((`users` `u` left join `departments` `d` on(`u`.`department_id` = `d`.`id`)) left join `users` `approver` on(`u`.`approved_by` = `approver`.`id`)) ;

CREATE INDEX idx_document_files_submission ON document_files(submission_id);
CREATE INDEX idx_faculty_submissions_composite ON faculty_document_submissions(faculty_id, document_type, semester, academic_year);

CREATE OR REPLACE VIEW `v_submission_tracker` AS 
SELECT 
    u.id AS user_id, 
    u.username AS username, 
    u.name AS name, 
    u.surname AS surname, 
    u.department_id AS department_id, 
    d.department_name AS department_name, 
    d.department_code AS department_code, 
    fds.academic_year AS academic_year, 
    fds.semester AS semester, 
    fds.document_type AS document_type, 
    COUNT(df.id) AS file_count, 
    MAX(df.uploaded_at) AS latest_upload, 
    MIN(df.uploaded_at) AS first_upload, 
    SUM(df.file_size) AS total_size, 
    fds.submitted_at AS submitted_at, 
    fds.submitted_by AS submitted_by, 
    CASE 
        WHEN COUNT(df.id) > 0 THEN 'uploaded' 
        ELSE 'not_uploaded' 
    END AS status 
FROM users u 
LEFT JOIN departments d ON u.department_id = d.id 
LEFT JOIN faculty_document_submissions fds ON u.id = fds.faculty_id 
LEFT JOIN document_files df ON fds.id = df.submission_id AND df.file_type = fds.document_type 
WHERE u.role = 'user' AND u.is_approved = 1 
GROUP BY u.id, fds.academic_year, fds.semester, fds.document_type;

ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_resource` (`resource_type`,`resource_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_activity_logs_user_action` (`user_id`,`action`);

ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_is_published` (`is_published`),
  ADD KEY `idx_is_deleted` (`is_deleted`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `announcements_updated_by_fk` (`updated_by`),
  ADD KEY `announcements_deleted_by_fk` (`deleted_by`),
  ADD KEY `idx_announcements_published` (`is_published`,`published_at`);
ALTER TABLE `announcements` ADD FULLTEXT KEY `announcement_search` (`title`,`content`,`summary`);

ALTER TABLE `announcement_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_view` (`announcement_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_code` (`department_code`);

ALTER TABLE `document_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `deleted_by` (`deleted_by`),
  ADD KEY `idx_file_type` (`file_type`),
  ADD KEY `idx_uploaded_at` (`uploaded_at`);

ALTER TABLE `document_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_comment_id` (`parent_comment_id`),
  ADD KEY `deleted_by` (`deleted_by`),
  ADD KEY `idx_comment_type` (`comment_type`),
  ADD KEY `idx_is_internal` (`is_internal`),
  ADD KEY `idx_created_at` (`created_at`);

ALTER TABLE `document_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_document_files_period` (`uploaded_by`,`academic_year`,`semester_period`),
  ADD KEY `idx_files_year_semester_type` (`academic_year`,`semester_period`,`file_type`);

ALTER TABLE `document_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_notification_type` (`notification_type`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tracking_code` (`tracking_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `target_department` (`target_department`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `deleted_by` (`deleted_by`),
  ADD KEY `idx_document_status` (`status`),
  ADD KEY `idx_document_type` (`document_type`),
  ADD KEY `idx_document_priority` (`priority`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_expected_completion` (`expected_completion`),
  ADD KEY `idx_is_deleted` (`is_deleted`);
ALTER TABLE `document_requests` ADD FULLTEXT KEY `document_search` (`title`,`description`);

ALTER TABLE `document_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_requirement` (`academic_year`,`semester`,`department_id`,`document_type`),
  ADD KEY `idx_year_semester` (`academic_year`,`semester`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `document_requirements_created_by_fk` (`created_by`);

ALTER TABLE `document_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_status_changed_at` (`changed_at`);

ALTER TABLE `document_submission_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `document_type` (`document_type`),
  ADD KEY `semester` (`semester`);

ALTER TABLE `document_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_document_type` (`document_type`),
  ADD KEY `idx_is_active` (`is_active`);

ALTER TABLE `document_workflows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `assigned_department` (`assigned_department`),
  ADD KEY `idx_step_number` (`step_number`),
  ADD KEY `idx_workflow_status` (`status`);

ALTER TABLE `faculty_document_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission` (`faculty_id`,`document_type`,`semester`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `submitted_by` (`submitted_by`),
  ADD KEY `idx_faculty_year_semester` (`faculty_id`,`academic_year`,`semester`),
  ADD KEY `idx_submissions_year_semester_user` (`academic_year`,`semester`,`faculty_id`);

ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `parent_file_id` (`parent_file_id`),
  ADD KEY `idx_is_deleted` (`is_deleted`),
  ADD KEY `idx_file_type` (`file_type`),
  ADD KEY `idx_public_token` (`public_token`),
  ADD KEY `idx_file_hash` (`file_hash`),
  ADD KEY `files_deleted_by_fk` (`deleted_by`),
  ADD KEY `files_downloaded_by_fk` (`last_downloaded_by`),
  ADD KEY `idx_files_uploaded_at` (`uploaded_at`),
  ADD KEY `idx_files_file_type_size` (`file_type`,`file_size`);
ALTER TABLE `files` ADD FULLTEXT KEY `file_search` (`original_name`,`description`);

ALTER TABLE `file_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_comment_id` (`parent_comment_id`);

ALTER TABLE `file_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `share_token` (`share_token`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `shared_by` (`shared_by`),
  ADD KEY `shared_with` (`shared_with`),
  ADD KEY `idx_file_shares_expires` (`expires_at`,`is_active`);

ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_is_deleted` (`is_deleted`),
  ADD KEY `idx_folder_path` (`folder_path`),
  ADD KEY `folders_deleted_by_fk` (`deleted_by`),
  ADD KEY `idx_folders_created_at` (`created_at`);
ALTER TABLE `folders` ADD FULLTEXT KEY `folder_search` (`folder_name`,`description`);

ALTER TABLE `folder_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `granted_by` (`granted_by`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posts_user_fk` (`user_id`),
  ADD KEY `posts_deleted_by_fk` (`deleted_by`),
  ADD KEY `idx_posts_created_at` (`created_at`),
  ADD KEY `idx_posts_visibility` (`visibility`),
  ADD KEY `idx_posts_pinned` (`is_pinned`),
  ADD KEY `idx_posts_deleted` (`is_deleted`);

ALTER TABLE `post_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_comments_post_fk` (`post_id`),
  ADD KEY `post_comments_user_fk` (`user_id`),
  ADD KEY `post_comments_parent_fk` (`parent_comment_id`),
  ADD KEY `post_comments_deleted_by_fk` (`deleted_by`),
  ADD KEY `idx_post_comments_created_at` (`created_at`),
  ADD KEY `idx_post_comments_deleted` (`is_deleted`);

ALTER TABLE `post_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_post_like` (`post_id`,`user_id`),
  ADD UNIQUE KEY `unique_comment_like` (`comment_id`,`user_id`),
  ADD KEY `post_likes_post_fk` (`post_id`),
  ADD KEY `post_likes_comment_fk` (`comment_id`),
  ADD KEY `post_likes_user_fk` (`user_id`),
  ADD KEY `idx_post_likes_created_at` (`created_at`);

ALTER TABLE `post_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_media_post_fk` (`post_id`),
  ADD KEY `idx_post_media_type` (`media_type`);

ALTER TABLE `post_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_notifications_user_fk` (`user_id`),
  ADD KEY `post_notifications_post_fk` (`post_id`),
  ADD KEY `post_notifications_comment_fk` (`comment_id`),
  ADD KEY `post_notifications_triggered_by_fk` (`triggered_by`),
  ADD KEY `idx_post_notifications_read` (`is_read`),
  ADD KEY `idx_post_notifications_created_at` (`created_at`);

ALTER TABLE `post_shares`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_shares_post_fk` (`post_id`),
  ADD KEY `post_shares_user_fk` (`user_id`),
  ADD KEY `idx_post_shares_created_at` (`created_at`);

ALTER TABLE `post_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_post_view` (`post_id`,`user_id`),
  ADD KEY `post_views_post_fk` (`post_id`),
  ADD KEY `post_views_user_fk` (`user_id`);

ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `fk_user_department` (`department_id`),
  ADD KEY `idx_email_verified` (`email_verified`),
  ADD KEY `idx_is_approved` (`is_approved`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `fk_user_created_by` (`created_by`),
  ADD KEY `fk_user_approved_by` (`approved_by`),
  ADD KEY `idx_users_last_login` (`last_login`);

ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `announcements_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `announcement_views`
  ADD CONSTRAINT `announcement_views_announcement_fk` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_views_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `document_attachments`
  ADD CONSTRAINT `document_attachments_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_attachments_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_attachments_uploaded_by_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `document_comments`
  ADD CONSTRAINT `document_comments_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_comments_parent_fk` FOREIGN KEY (`parent_comment_id`) REFERENCES `document_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_comments_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_comments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `document_files`
  ADD CONSTRAINT `document_files_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `faculty_document_submissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `document_notifications`
  ADD CONSTRAINT `document_notifications_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `document_requests`
  ADD CONSTRAINT `document_requests_assigned_to_fk` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_requests_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_requests_target_dept_fk` FOREIGN KEY (`target_department`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_requests_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_requests_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `document_requirements`
  ADD CONSTRAINT `document_requirements_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_requirements_dept_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

ALTER TABLE `document_status_history`
  ADD CONSTRAINT `document_status_changed_by_fk` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_status_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE;

ALTER TABLE `document_templates`
  ADD CONSTRAINT `document_templates_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_templates_dept_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_templates_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `document_workflows`
  ADD CONSTRAINT `document_workflows_assigned_dept_fk` FOREIGN KEY (`assigned_department`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_workflows_assigned_to_fk` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_workflows_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE;

ALTER TABLE `faculty_document_submissions`
  ADD CONSTRAINT `faculty_document_submissions_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `faculty_document_submissions_ibfk_2` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `files`
  ADD CONSTRAINT `files_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `files_downloaded_by_fk` FOREIGN KEY (`last_downloaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_parent_fk` FOREIGN KEY (`parent_file_id`) REFERENCES `files` (`id`) ON DELETE SET NULL;

ALTER TABLE `file_comments`
  ADD CONSTRAINT `file_comments_file_fk` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_comments_parent_fk` FOREIGN KEY (`parent_comment_id`) REFERENCES `file_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_comments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `file_shares`
  ADD CONSTRAINT `file_shares_file_fk` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_shares_shared_by_fk` FOREIGN KEY (`shared_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_shares_shared_with_fk` FOREIGN KEY (`shared_with`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `folders`
  ADD CONSTRAINT `folders_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `folders_department_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folders_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE;

ALTER TABLE `folder_permissions`
  ADD CONSTRAINT `folder_permissions_department_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folder_permissions_folder_fk` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folder_permissions_granted_by_fk` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folder_permissions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `posts`
  ADD CONSTRAINT `posts_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `posts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `post_comments`
  ADD CONSTRAINT `post_comments_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `post_comments_parent_fk` FOREIGN KEY (`parent_comment_id`) REFERENCES `post_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_comments_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_comments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `post_likes`
  ADD CONSTRAINT `post_likes_comment_fk` FOREIGN KEY (`comment_id`) REFERENCES `post_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_likes_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_likes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `post_media`
  ADD CONSTRAINT `post_media_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

ALTER TABLE `post_notifications`
  ADD CONSTRAINT `post_notifications_comment_fk` FOREIGN KEY (`comment_id`) REFERENCES `post_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_notifications_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_notifications_triggered_by_fk` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `post_shares`
  ADD CONSTRAINT `post_shares_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_shares_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `post_views`
  ADD CONSTRAINT `post_views_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_views_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;
