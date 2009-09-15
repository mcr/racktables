-- This file is intentionally kept in extended INSERT syntax
-- (1 query = N rows) to generate less diff noise on column
-- name change.

INSERT INTO `Attribute` (`id`, `type`, `name`) VALUES
(1,'string','OEM S/N 1'),
(2,'dict','HW type'),
(3,'string','FQDN'),
(4,'dict','SW type'),
(5,'string','SW version'),
(6,'uint','number of ports'),
(7,'float','max. current, Ampers'),
(8,'float','power load, percents'),
(14,'string','contact person'),
(13,'float','max power, Watts'),
(16,'uint','flash memory, MB'),
(17,'uint','DRAM, MB'),
(18,'uint','CPU, MHz'),
(20,'string','OEM S/N 2'),
(21,'string','support contract expiration'),
(22,'string','HW warranty expiration'),
(24,'string','SW warranty expiration'),
(25,'string','UUID');

INSERT INTO `AttributeMap` (`objtype_id`, `attr_id`, `chapter_id`) VALUES
(2,2,27),
(4,1,NULL),
(4,2,11),
(4,3,NULL),
(4,4,13),
(4,14,NULL),
(4,21,NULL),
(4,22,NULL),
(4,25,NULL),
(4,24,NULL),
(5,1,NULL),
(5,2,18),
(6,1,NULL),
(6,2,19),
(6,20,NULL),
(7,1,NULL),
(7,2,17),
(7,3,NULL),
(7,4,16),
(7,5,NULL),
(7,14,NULL),
(7,16,NULL),
(7,17,NULL),
(7,18,NULL),
(7,21,NULL),
(7,22,NULL),
(7,24,NULL),
(8,1,NULL),
(8,2,12),
(8,3,NULL),
(8,4,14),
(8,5,NULL),
(8,14,NULL),
(8,16,NULL),
(8,17,NULL),
(8,18,NULL),
(8,20,NULL),
(8,21,NULL),
(8,22,NULL),
(8,24,NULL),
(9,6,NULL),
(12,1,NULL),
(12,3,NULL),
(12,7,NULL),
(12,8,NULL),
(12,13,NULL),
(12,20,NULL),
(445,1,NULL),
(445,2,21),
(445,3,NULL),
(445,5,NULL),
(445,14,NULL),
(445,22,NULL),
(447,1,NULL),
(447,2,22),
(447,3,NULL),
(447,5,NULL),
(447,14,NULL),
(447,22,NULL),
(15,2,23),
(798,1,NULL),
(798,2,24),
(798,3,NULL),
(798,5,NULL),
(798,14,NULL),
(798,16,NULL),
(798,17,NULL),
(798,18,NULL),
(798,20,NULL),
(798,21,NULL),
(798,22,NULL),
(798,24,NULL),
(965,1,NULL),
(965,3,NULL),
(965,2,25),
(1055,2,26);

INSERT INTO `Chapter` (`id`, `sticky`, `name`) VALUES
(1,'yes','RackObjectType'),
(2,'yes','PortOuterInterface'),
(11,'no','server models'),
(12,'no','network switch models'),
(13,'no','server OS type'),
(14,'no','switch OS type'),
(16,'no','router OS type'),
(17,'no','router models'),
(18,'no','disk array models'),
(19,'no','tape library models'),
(21,'no','KVM switch models'),
(22,'no','multiplexer models'),
(23,'no','console models'),
(24,'no','network security models'),
(25,'no','wireless models'),
(26,'no','fibre channel switch models'),
(27,'no','PDU models');

INSERT INTO `PortInnerInterface` VALUES
(1,'hardwired'),
(2,'SFP-100'),
(3,'GBIC'),
(4,'SFP-1000'),
(5,'XENPAK'),
(6,'X2'),
(7,'XPAK'),
(8,'XFP'),
(9,'SFP+');

INSERT INTO `PortInterfaceCompat` VALUES
(2,1208),(2,1195),(2,1196),(2,1197),(2,1198),(2,1199),(2,1200),(2,1201),
(3,1078),(3,24),(3,34),(3,1202),(3,1203),(3,1204),(3,1205),(3,1206),(3,1207),
(4,1077),(4,24),(4,34),(4,1202),(4,1203),(4,1204),(4,1205),(4,1206),(4,1207),
(5,1079),(5,30),(5,35),(5,36),(5,37),(5,38),(5,39),(5,40),
(6,1080),(6,30),(6,35),(6,36),(6,37),(6,38),(6,39),(6,40),
(7,1081),(7,30),(7,35),(7,36),(7,37),(7,38),(7,39),(7,40),
(8,1082),(8,30),(8,35),(8,36),(8,37),(8,38),(8,39),(8,40),
(9,1084),(9,30),(9,35),(9,36),(9,37),(9,38),(9,39),(9,40),
(1,16),(1,19),(1,24),(1,29),(1,31),(1,33),(1,446),(1,681),(1,682),(1,1322);

INSERT INTO `PortCompat` (`type1`, `type2`) VALUES
(17,17),
(18,18),
(19,19),
(20,20),
(21,21),
(21,1195),
(22,22),
(22,1196),
(23,23),
(23,1196),
(24,24),
(25,25),
(26,26),
(27,27),
(28,28),
(18,19),
(19,18),
(18,24),
(24,18),
(19,24),
(24,19),
(29,29),
(20,21),
(20,1083),
(20,1195),
(21,20),
(21,1083),
(22,23),
(23,22),
(25,26),
(25,1202),
(26,25),
(26,1202),
(27,28),
(27,1204),
(28,27),
(28,1204),
(30,30),
(16,1322),
(1322,16),
(29,681),
(29,682),
(32,32),
(33,446),
(34,34),
(35,35),
(36,36),
(37,37),
(38,38),
(39,39),
(40,40),
(41,41),
(439,439),
(446,33),
(681,29),
(681,681),
(681,682),
(682,29),
(682,681),
(682,682),
(1077,1077),
(1083,20),
(1083,21),
(1083,1083),
(1083,1195),
(1084,1084),
(1087,1087),
(1195,20),
(1195,21),
(1195,1083),
(1195,1195),
(1196,22),
(1196,23),
(1196,1196),
(1197,1197),
(1198,1199),
(1199,1198),
(1200,1200),
(1201,1201),
(1202,25),
(1202,26),
(1202,1202),
(1203,1203),
(1204,27),
(1204,28),
(1204,1204),
(1205,1205),
(1206,1207),
(1207,1206),
(1209,1209),
(1210,1210),
(1211,1211),
(1212,1212),
(1213,1213),
(1214,1214),
(1215,1215),
(1216,1216),
(1217,1217),
(1218,1218),
(1219,1219),
(1220,1220),
(1221,1221),
(1222,1222),
(1223,1223),
(1224,1224),
(1225,1225),
(1226,1226),
(1227,1227),
(1228,1228),
(1229,1229),
(1230,1230),
(1231,1231),
(1232,1232),
(1233,1233),
(1234,1234),
(1235,1235),
(1236,1236),
(1237,1237),
(1238,1238),
(1239,1239),
(1240,1240),
(1241,1241),
(1242,1242),
(1243,1243),
(1244,1244),
(1245,1245),
(1246,1246),
(1247,1247),
(1248,1248),
(1249,1249),
(1250,1250),
(1251,1251),
(1252,1252),
(1253,1253),
(1254,1254),
(1255,1255),
(1256,1256),
(1257,1257),
(1258,1258),
(1259,1259),
(1260,1260),
(1261,1261),
(1262,1262),
(1263,1263),
(1264,1264),
(1265,1265),
(1266,1266),
(1267,1267),
(1268,1268),
(1269,1269),
(1270,1270),
(1271,1271),
(1272,1272),
(1273,1273),
(1274,1274),
(1275,1275),
(1276,1276),
(1277,1277),
(1278,1278),
(1279,1279),
(1280,1280),
(1281,1281),
(1282,1282),
(1283,1283),
(1284,1284),
(1285,1285),
(1286,1286),
(1287,1287),
(1288,1288),
(1289,1289),
(1290,1290),
(1291,1291),
(1292,1292),
(1293,1293),
(1294,1294),
(1295,1295),
(1296,1296),
(1297,1297),
(1298,1298),
(1299,1299),
(1300,1300),
(1316,1316);

INSERT INTO `Config` (varname, varvalue, vartype, emptyok, is_hidden, description) VALUES
('color_F','8fbfbf','string','no','yes','HSV: 180-25-75. Free atoms, they are available for allocation to objects.'),
('color_A','bfbfbf','string','no','yes','HSV: 0-0-75. Absent atoms.'),
('color_U','bf8f8f','string','no','yes','HSV: 0-25-75. Unusable atoms. Some problems keep them from being free.'),
('color_T','408080','string','no','yes','HSV: 180-50-50. Taken atoms, object_id should be set for such.'),
('color_Th','80ffff','string','no','yes','HSV: 180-50-100. Taken atoms with highlight. They are not stored in the database and are only used for highlighting.'),
('color_Tw','804040','string','no','yes','HSV: 0-50-50. Taken atoms with object problem. This is detected at runtime.'),
('color_Thw','ff8080','string','no','yes','HSV: 0-50-100. An object can be both current and problematic. We run highlightObject() first and markupObjectProblems() second.'),
('MASSCOUNT','15','uint','no','no','&quot;Fast&quot; form is this many records tall'),
('MAXSELSIZE','30','uint','no','no','&lt;SELECT&gt; lists height'),
('enterprise','MyCompanyName','string','no','no','Organization name'),
('ROW_SCALE','2','uint','no','no','Picture scale for rack row display'),
('PORTS_PER_ROW','12','uint','no','no','Ports per row in VLANs tab'),
('IPV4_ADDRS_PER_PAGE','256','uint','no','no','IPv4 addresses per page'),
('DEFAULT_RACK_HEIGHT','42','uint','yes','no','Default rack height'),
('DEFAULT_SLB_VS_PORT','','uint','yes','no','Default port of SLB virtual service'),
('DEFAULT_SLB_RS_PORT','','uint','yes','no','Default port of SLB real server'),
('DETECT_URLS','no','string','yes','no','Detect URLs in text fields'),
('RACK_PRESELECT_THRESHOLD','1','uint','no','no','Rack pre-selection threshold'),
('DEFAULT_IPV4_RS_INSERVICE','no','string','no','no','Inservice status for new SLB real servers'),
('AUTOPORTS_CONFIG','4 = 1*33*kvm + 2*24*eth%u;15 = 1*446*kvm','string','yes','no','AutoPorts configuration'),
('DEFAULT_OBJECT_TYPE','4','uint','yes','no','Default object type for new objects'),
('SHOW_EXPLICIT_TAGS','yes','string','no','no','Show explicit tags'),
('SHOW_IMPLICIT_TAGS','yes','string','no','no','Show implicit tags'),
('SHOW_AUTOMATIC_TAGS','no','string','no','no','Show automatic tags'),
('IPV4_AUTO_RELEASE','1','uint','no','no','Auto-release IPv4 addresses on allocation'),
('SHOW_LAST_TAB','no','string','yes','no','Remember last tab shown for each page'),
('EXT_IPV4_VIEW','yes','string','no','no','Extended IPv4 view'),
('TREE_THRESHOLD','25','uint','yes','no','Tree view auto-collapse threshold'),
('IPV4_JAYWALK','no','string','no','no','Enable IPv4 address allocations w/o covering network'),
('ADDNEW_AT_TOP','yes','string','no','no','Render "add new" line at top of the list'),
('IPV4_TREE_SHOW_USAGE','yes','string','no','no','Show address usage in IPv4 tree'),
('PREVIEW_TEXT_MAXCHARS','10240','uint','yes','no','Max chars for text file preview'),
('PREVIEW_TEXT_ROWS','25','uint','yes','no','Rows for text file preview'),
('PREVIEW_TEXT_COLS','80','uint','yes','no','Columns for text file preview'),
('PREVIEW_IMAGE_MAXPXS','320','uint','yes','no','Max pixels per axis for image file preview'),
('VENDOR_SIEVE','','string','yes','no','Vendor sieve configuration'),
('IPV4LB_LISTSRC','{$typeid_4}','string','yes','no','List source: IPv4 load balancers'),
('IPV4OBJ_LISTSRC','{$typeid_4} or {$typeid_7} or {$typeid_8} or {$typeid_12} or {$typeid_445} or {$typeid_447}','string','yes','no','List source: IPv4-enabled objects'),
('IPV4NAT_LISTSRC','{$typeid_4} or {$typeid_7} or {$typeid_8}','string','yes','no','List source: IPv4 NAT performers'),
('ASSETWARN_LISTSRC','{$typeid_4} or {$typeid_7} or {$typeid_8}','string','yes','no','List source: object, for which asset tag should be set'),
('NAMEWARN_LISTSRC','{$typeid_4} or {$typeid_7} or {$typeid_8}','string','yes','no','List source: object, for which common name should be set'),
('RACKS_PER_ROW','12','uint','yes','no','Racks per row'),
('FILTER_PREDICATE_SIEVE','','string','yes','no','Predicate sieve regex(7)'),
('FILTER_DEFAULT_ANDOR','or','string','no','no','Default list filter boolean operation (or/and)'),
('FILTER_SUGGEST_ANDOR','yes','string','no','no','Suggest and/or selector in list filter'),
('FILTER_SUGGEST_TAGS','yes','string','no','no','Suggest tags in list filter'),
('FILTER_SUGGEST_PREDICATES','yes','string','no','no','Suggest predicates in list filter'),
('FILTER_SUGGEST_EXTRA','no','string','no','no','Suggest extra expression in list filter'),
('DEFAULT_SNMP_COMMUNITY','public','string','no','no','Default SNMP Community string'),
('IPV4_ENABLE_KNIGHT','yes','string','no','no','Enable IPv4 knight feature'),
('TAGS_TOPLIST_SIZE','50','uint','yes','no','Tags top list size'),
('TAGS_QUICKLIST_SIZE','20','uint','no','no','Tags quick list size'),
('TAGS_QUICKLIST_THRESHOLD','50','uint','yes','no','Tags quick list threshold'),
('ENABLE_MULTIPORT_FORM','no','string','no','no','Enable "Add/update multiple ports" form'),
('DEFAULT_PORT_IIF_ID','1','uint','no','no','Default port inner interface ID'),
('DEFAULT_PORT_OIF_IDS','1=24; 3=1078; 4=1077; 5=1079; 6=1080; 8=1082; 9=1084','string','no','no','Default port outer interface IDs'),
('IPV4_TREE_RTR_AS_CELL','yes','string','no','no','Show full router info for each network in IPv4 tree view'),
('DB_VERSION','0.17.4','string','no','yes','Database version.');

INSERT INTO `Script` VALUES ('RackCode','allow {$userid_1}');
