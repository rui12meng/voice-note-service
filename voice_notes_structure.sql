-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: voice_notes
-- ------------------------------------------------------
-- Server version	8.0.45-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ai_analysis_types`
--

DROP TABLE IF EXISTS `ai_analysis_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_analysis_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '展示用标题：洞察/情绪/闪卡/行动/习惯...',
  `json_schema` json NOT NULL COMMENT '每个类型的 JSON Schema 定义',
  `schema_version` int NOT NULL DEFAULT '1' COMMENT '支持未来演进',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI分析类型配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_analysis_usage`
--

DROP TABLE IF EXISTS `ai_analysis_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_analysis_usage` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `note_id` bigint NOT NULL COMMENT '关联 notes.id（可为空，如测试调用）',
  `user_id` bigint NOT NULL COMMENT '触发分析的用户ID',
  `ai_model` varchar(100) NOT NULL COMMENT '实际调用的模型名，如 gpt-4o, qwen-max',
  `prompt_tokens` int NOT NULL COMMENT '输入token数',
  `completion_tokens` int NOT NULL COMMENT '输出token数',
  `total_tokens` int GENERATED ALWAYS AS ((`prompt_tokens` + `completion_tokens`)) STORED,
  `cost_usd` decimal(10,6) DEFAULT NULL COMMENT '估算费用（USD），便于财务对账',
  `duration_ms` int DEFAULT NULL COMMENT 'API 调用耗时（毫秒）',
  `request_id` varchar(100) DEFAULT NULL COMMENT 'AI平台返回的唯一请求ID，用于追踪',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_note_id` (`note_id`),
  KEY `idx_model_created` (`ai_model`,`created_at`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='AI分析调用资源消耗记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_prompt_templates`
--

DROP TABLE IF EXISTS `ai_prompt_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_prompt_templates` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `prompt_key` varchar(64) NOT NULL COMMENT '业务标识，如 diary_analysis',
  `version` varchar(16) NOT NULL COMMENT '版本号，如 v1, v2',
  `scene` varchar(64) DEFAULT 'default' COMMENT '使用场景，可用于区分不同分析策略',
  `system_prompt` text NOT NULL COMMENT 'system 角色提示词',
  `user_prompt_template` text NOT NULL COMMENT '带变量的 user prompt 模板',
  `variables` json NOT NULL COMMENT '允许注入的变量及类型说明',
  `output_contract` json NOT NULL COMMENT '输出结构说明（聚合层，不是子表）',
  `is_active` tinyint(1) DEFAULT '1',
  `remark` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prompt` (`prompt_key`,`version`,`scene`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `emotion_tags`
--

DROP TABLE IF EXISTS `emotion_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `emotion_tags` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `note_id` bigint NOT NULL,
  `emotion_type_id` bigint NOT NULL,
  `intensity` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emotion_type_id` (`emotion_type_id`),
  KEY `idx_intensity` (`intensity`),
  KEY `idx_diary_id` (`note_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `habit_sync_records`
--

DROP TABLE IF EXISTS `habit_sync_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `habit_sync_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL COMMENT '用户ID',
  `sync_date` date NOT NULL COMMENT '同步日期（如 2026-01-20）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date` (`user_id`,`sync_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `note_ai_analysis`
--

DROP TABLE IF EXISTS `note_ai_analysis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `note_ai_analysis` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `note_id` bigint NOT NULL,
  `analysis_type_name` varchar(64) NOT NULL COMMENT '类型名称，如 emotion, topic, summary',
  `ai_model_version` varchar(100) DEFAULT NULL COMMENT '分析版本（用于区分算法/模型迭代）',
  `analyzed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '分析时间',
  `analysis_data` json NOT NULL COMMENT '具体分析内容，结构随类型变化',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '逻辑删除：0=正常, 1=已删除',
  `deleted_at` datetime DEFAULT NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_note_type_active` (`note_id`,`analysis_type_name`,((case when (`is_deleted` = 0) then 0 else NULL end))),
  KEY `idx_note_deleted_type` (`note_id`,`is_deleted`,`analysis_type_name`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='日记AI分析结果表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `note_tags`
--

DROP TABLE IF EXISTS `note_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `note_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `note_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(64) NOT NULL COMMENT '原始标签名（如 "happy", "hike"）',
  `normalized_name` varchar(64) NOT NULL COMMENT '标准化小写（用于匹配）',
  `source` enum('ai','user') NOT NULL DEFAULT 'ai',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_note` (`note_id`),
  KEY `idx_user_raw` (`user_id`,`name`),
  KEY `idx_normalized` (`normalized_name`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notes`
--

DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `note_type` tinyint NOT NULL COMMENT '1:audio, 2:text, 3:image',
  `title` varchar(255) DEFAULT NULL COMMENT 'AI生成标题（可为空）',
  `content` mediumtext,
  `summary` varchar(500) DEFAULT NULL COMMENT 'AI生成摘要（可为空）',
  `media_url` json DEFAULT NULL COMMENT '音频/图片URL列表，支持多文件存储',
  `is_analyzed` tinyint NOT NULL DEFAULT '0' COMMENT 'AI分析状态：0=未分析，1=已分析',
  `analyzed_at` datetime DEFAULT NULL COMMENT 'AI分析完成时间',
  `ai_model_version` varchar(100) DEFAULT NULL COMMENT 'AI模型版本（用于追溯）',
  `moderation_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '审核状态：1=允许，2=警告，3=拦截',
  `is_deleted` tinyint NOT NULL DEFAULT '0' COMMENT '逻辑删除：0=正常, 1=已删除',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL COMMENT '软删除时间',
  `is_critical` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否危机状态：1=是，0=否',
  PRIMARY KEY (`id`),
  KEY `idx_user_deleted_id` (`user_id`,`is_deleted`,`id` DESC),
  KEY `idx_id_user_deleted` (`id`,`user_id`,`is_deleted`),
  FULLTEXT KEY `ft_title_content_summary` (`title`,`content`,`summary`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户日记主表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reflection_ai_analysis`
--

DROP TABLE IF EXISTS `reflection_ai_analysis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reflection_ai_analysis` (
  `reflection_id` bigint unsigned NOT NULL COMMENT '关联 user_reflections.id',
  `type` enum('daily','weekly') NOT NULL COMMENT '冗余字段，避免 JOIN',
  `analysis` json NOT NULL COMMENT 'AI分析结果（结构见下方规范）',
  `action_suggestions` json DEFAULT NULL COMMENT '下一步调整建议',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`reflection_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='复盘AI分析结果表（JSON结构化存储）';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_actions`
--

DROP TABLE IF EXISTS `user_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_actions` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `note_id` bigint DEFAULT NULL,
  `habit_id` bigint DEFAULT NULL,
  `title` varchar(255) NOT NULL COMMENT '任务标题',
  `content` text COMMENT '任务详细描述（可选）',
  `source` enum('ai','user') NOT NULL DEFAULT 'ai',
  `status` tinyint(1) DEFAULT '0' COMMENT '0=未完成, 1=已完成, 2=已跳过, 3=已删除',
  `is_deleted` tinyint(1) DEFAULT '0',
  `due_date` date DEFAULT NULL COMMENT '计划完成时间',
  `complete_time` datetime DEFAULT NULL COMMENT '实际打卡时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `streak` int NOT NULL DEFAULT '0' COMMENT '连续打卡快照',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_uid_habit_due` (`user_id`,`habit_id`,`due_date`),
  KEY `idx_uid_note_id` (`user_id`,`note_id`,`is_deleted`),
  KEY `idx_uid_status_due` (`user_id`,`status`,`due_date`,`is_deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_auth`
--

DROP TABLE IF EXISTS `user_auth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_auth` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` bigint unsigned NOT NULL COMMENT '关联 users.id',
  `auth_type` varchar(32) NOT NULL COMMENT '认证类型: google, apple, guest 等',
  `identifier` varchar(255) NOT NULL COMMENT '登录唯一标识: 用户名/邮箱/手机号/第三方UID',
  `metadata` json DEFAULT NULL COMMENT 'payload解析信息(JSON)',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '绑定状态: 1=有效,0=禁用,2=已解绑',
  `last_login_at` datetime DEFAULT NULL COMMENT '最近登录时间',
  `is_deleted` tinyint DEFAULT '0' COMMENT '0正常,1注销',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_auth` (`auth_type`,`identifier`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户认证信息表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_devices`
--

DROP TABLE IF EXISTS `user_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` bigint unsigned NOT NULL COMMENT '关联用户ID',
  `device_id` char(64) NOT NULL COMMENT '设备唯一标识ID(如IMEI/IDFA等)',
  `device_type` varchar(32) DEFAULT NULL COMMENT '设备类型: ios, android, web, mac, windows',
  `device_name` varchar(128) DEFAULT NULL COMMENT '设备名称: iPhone 15, MacBook Pro, Chrome',
  `os_version` varchar(64) DEFAULT NULL COMMENT '操作系统版本',
  `app_version` varchar(32) DEFAULT NULL COMMENT '应用版本',
  `push_token` varchar(255) DEFAULT NULL COMMENT '推送服务Token(APNs/FCM)',
  `ip_address` varchar(64) DEFAULT NULL COMMENT '最后登录IP',
  `login_location` varchar(128) DEFAULT NULL COMMENT '地理位置',
  `first_login_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '首次登录时间',
  `last_login_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '最近登录时间',
  `is_trusted` tinyint(1) DEFAULT '0' COMMENT '是否信任设备: 1=是, 0=否',
  `notification_enabled` tinyint DEFAULT '0' COMMENT '是否开启通知，默认0不开启，1开启',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '状态: 1=正常,0=禁用,2=已解绑',
  `device_info` json DEFAULT NULL COMMENT '设备扩展信息(JSON): 分辨率,网络类型,语言等',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_device` (`user_id`,`device_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_device_type` (`device_type`),
  KEY `idx_status` (`status`),
  KEY `idx_last_login_at` (`last_login_at`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户设备表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_habit_schedules`
--

DROP TABLE IF EXISTS `user_habit_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_habit_schedules` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `habit_id` bigint NOT NULL,
  `frequency_type` enum('daily','weekly','monthly','interval') NOT NULL,
  `frequency_config` json NOT NULL COMMENT '频率配置',
  `timezone` varchar(32) DEFAULT 'UTC',
  `is_deleted` tinyint DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_habit` (`habit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_habits`
--

DROP TABLE IF EXISTS `user_habits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_habits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `note_id` bigint unsigned DEFAULT NULL COMMENT '来源日记（AI提取时才有）',
  `habit_name` varchar(100) NOT NULL,
  `habit_desc` varchar(255) DEFAULT NULL,
  `remind_time` time DEFAULT NULL COMMENT '提醒时间（08:30:00）',
  `anchor_date` date NOT NULL COMMENT '规则锚点日期（最近一次应执行日）',
  `interval_num` int unsigned DEFAULT NULL COMMENT '执行间隔天数（v0.1：每 N 天）',
  `interval_unit` varchar(50) DEFAULT NULL COMMENT '时间间隔单位',
  `streak_count` int unsigned NOT NULL DEFAULT '0' COMMENT '历史总打卡次数',
  `current_streak` int unsigned NOT NULL DEFAULT '0' COMMENT '历史总打卡次数',
  `last_done_date` date DEFAULT NULL COMMENT '最后一次打卡日期',
  `note_summary` varchar(300) DEFAULT NULL COMMENT '如果习惯来源日记，冗余notes摘要',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态：0=关闭，1=开启',
  `is_deleted` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '软删除标记',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`,`status`,`is_deleted`),
  KEY `idx_user_anchor` (`user_id`,`anchor_date`),
  KEY `idx_user_last_done` (`user_id`,`last_done_date`),
  FULLTEXT KEY `ft_habit_text` (`habit_name`,`habit_desc`,`note_summary`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户习惯主表（状态表）';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_info`
--

DROP TABLE IF EXISTS `user_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_info` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` bigint unsigned NOT NULL COMMENT '关联 users.id',
  `nickname` varchar(64) DEFAULT NULL COMMENT '昵称',
  `email` varchar(128) DEFAULT NULL COMMENT '邮箱',
  `avatar_url` varchar(255) DEFAULT NULL COMMENT '头像地址',
  `gender` tinyint DEFAULT '0' COMMENT '0=未知,1=男,2=女',
  `birthday` date DEFAULT NULL COMMENT '生日',
  `timezone` varchar(64) DEFAULT NULL COMMENT '时区',
  `language` varchar(16) DEFAULT NULL COMMENT '语言',
  `is_deleted` tinyint DEFAULT '0' COMMENT '0正常,1注销',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_uid` (`user_id`),
  KEY `idx_uid_deleted` (`user_id`,`is_deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户资料扩展表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_logs`
--

DROP TABLE IF EXISTS `user_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` bigint unsigned NOT NULL COMMENT '关联用户ID',
  `device_id` char(64) DEFAULT NULL COMMENT '设备唯一标识',
  `log_type` varchar(64) NOT NULL COMMENT '日志类型：login、logout、update_profile、oauth_bind、password_change、purchase 等',
  `action` varchar(128) DEFAULT NULL COMMENT '具体行为或接口标识',
  `description` varchar(512) DEFAULT NULL COMMENT '行为描述，便于审计',
  `ip_address` varchar(64) DEFAULT NULL COMMENT '操作IP',
  `user_agent` varchar(255) DEFAULT NULL COMMENT '浏览器或客户端UA信息',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_log_type` (`log_type`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户操作日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_reflections`
--

DROP TABLE IF EXISTS `user_reflections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_reflections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` enum('daily','weekly') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `satisfaction_score` tinyint unsigned DEFAULT NULL,
  `primary_emotion` varchar(20) DEFAULT NULL,
  `daily_self_summary` text,
  `weekly_focus_for_next` text,
  `sync_to_diary` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否同步到日记',
  `generate_next_action` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否生成下一步计划/建议',
  `action_ids` json NOT NULL COMMENT '参与本次复盘的 action_id 列表',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_type_period` (`user_id`,`type`,`start_date`,`end_date`),
  KEY `idx_user_type_enddate` (`user_id`,`type`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户复盘主表（用户输入部分）';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` bigint unsigned NOT NULL COMMENT '关联 users.id',
  `jti` varchar(512) NOT NULL COMMENT '生成全局唯一的 jti（JWT ID）',
  `session_token` varchar(512) NOT NULL COMMENT '会话令牌',
  `refresh_token` varchar(512) DEFAULT NULL COMMENT '刷新令牌（可选）',
  `device_type` varchar(32) DEFAULT NULL COMMENT '设备类型: ''web'',''ios'',''android'',''windows'',''macos'',''other''',
  `device_id` varchar(100) DEFAULT NULL COMMENT '设备唯一标识',
  `device_name` varchar(128) DEFAULT NULL COMMENT '设备名或浏览器信息',
  `device_info` json DEFAULT NULL COMMENT '设备详细信息JSON',
  `ip_address` varchar(64) DEFAULT NULL COMMENT '登录IP',
  `user_agent` varchar(255) DEFAULT NULL COMMENT '浏览器UA或设备UA',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '会话状态: 1=有效,0=失效,2=登出',
  `expire_at` datetime DEFAULT NULL COMMENT '会话到期时间',
  `refresh_expires_at` datetime DEFAULT NULL COMMENT '刷新令牌过期时间',
  `last_active_at` datetime DEFAULT NULL COMMENT '最后活跃时间（续期或心跳）',
  `is_deleted` tinyint DEFAULT '0' COMMENT '0正常,1注销',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_jti` (`jti`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_lastactive` (`last_active_at`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户会话表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `uuid` varchar(40) NOT NULL COMMENT '全局唯一标识 ，对外 UID',
  `username` varchar(64) DEFAULT NULL COMMENT '用户名，用于登录和显示',
  `email` varchar(128) DEFAULT NULL COMMENT '邮箱',
  `register_type` varchar(32) DEFAULT NULL COMMENT '注册来源(''apple'',''google'',''guest'')',
  `is_guest` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1=访客,0=用户',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '状态： 0-禁用 1-正常',
  `is_deleted` tinyint DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_uid` (`uuid`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户主表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-10 15:55:52
