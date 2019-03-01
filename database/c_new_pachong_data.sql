/*
Navicat MySQL Data Transfer

Source Server         : 127.0.0.1
Source Server Version : 50711
Source Host           : 127.0.0.1:3306
Source Database       : new_cloth

Target Server Type    : MYSQL
Target Server Version : 50711
File Encoding         : 65001

Date: 2019-03-01 15:45:29
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `c_new_pachong_data`
-- ----------------------------
DROP TABLE IF EXISTS `c_new_pachong_data`;
CREATE TABLE `c_new_pachong_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(15) DEFAULT NULL,
  `value` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='爬虫数据';

-- ----------------------------
-- Records of c_new_pachong_data
-- ----------------------------
INSERT INTO `c_new_pachong_data` VALUES ('1', 'current_page', '101');
