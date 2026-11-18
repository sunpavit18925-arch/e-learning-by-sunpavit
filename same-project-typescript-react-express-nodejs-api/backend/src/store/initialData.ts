import {
  Role,
  User,
  Document,
  Equipment,
  Vehicle,
  VehicleAssignment,
  Borrowing,
  MeetingReport,
  SystemLog,
  TeamSet,
  RoleType
} from "../types/index.js";

export const initialRoles: Role[] = [
  {
    id: "super_admin",
    name: "Super Admin (ผู้ดูแลระบบสูงสุด)",
    department: "ศูนย์เทคโนโลยีสารสนเทศ",
    description: "มีสิทธิ์เต็มในการบริหารจัดการระบบ ผู้ใช้ หนังสือ ยานพาหนะ อุปกรณ์ และตรวจสอบ System Logs ทั้งหมด"
  },
  {
    id: "admin",
    name: "Admin (ผู้ดูแลระบบฝ่ายสารบรรณ)",
    department: "กองกลาง ฝ่ายสารบรรณ",
    description: "ลงรับหนังสือราชการ บันทึกภารกิจ มอบหมายงานไปยังฝ่ายปฏิบัติการ และจัดการผู้ใช้ทั่วไป"
  },
  {
    id: "head_operation",
    name: "หัวหน้าฝ่ายปฏิบัติการ (Head of Operations)",
    department: "Ceremony Operations & Production Division",
    description: "อนุมัติภารกิจ แต่งตั้งผู้ประสานงานและทีมงานลงพื้นที่ จัดชุดปฏิบัติงานส่วนกลาง และบันทึกยืมอุปกรณ์"
  },
  {
    id: "coordinator_operation",
    name: "ผู้ประสานงานฝ่ายปฏิบัติการ (Field Coordinator)",
    department: "Ceremony Operations & Production Division",
    description: "รับทราบภารกิจ ประสานงานหน่วยงานเจ้าภาพ สำรวจพื้นที่ และบันทึกรายงานการประชุม/ผังพิธีการ"
  },
  {
    id: "staff_operation",
    name: "เจ้าหน้าที่ฝ่ายปฏิบัติการ (Operations Staff)",
    department: "Ceremony Operations & Production Division",
    description: "รับทราบงานที่ได้รับมอบหมาย ดูรายละเอียดแผนงาน และพิมพ์บันทึกข้อความสรุปผล"
  },
  {
    id: "head_vehicle",
    name: "หัวหน้าฝ่ายยานพาหนะ (Head of Vehicle Fleet)",
    department: "ฝ่ายยานพาหนะและขนส่ง",
    description: "จัดสรรรถราชการและพนักงานขับรถตามภารกิจ ตรวจสอบคุณสมบัติคนขับ และควบคุมการยืมอุปกรณ์ยานยนต์"
  },
  {
    id: "staff_vehicle",
    name: "เจ้าหน้าที่/พลขับ (Driver / Fleet Staff)",
    department: "ฝ่ายยานพาหนะและขนส่ง",
    description: "ตรวจสอบกำหนดการเดินทาง ภารกิจที่ต้องปฏิบัติการเดินรถ และยืนยันการรับงาน"
  },
  {
    id: "pending",
    name: "ผู้ใช้ใหม่รออนุมัติสิทธิ์ (Pending Approval)",
    department: "สำนักผังเมืองรวม",
    description: "บัญชีเข้าสู่ระบบได้แต่ยังไม่ได้รับมอบหมายสิทธิ์ ปฏิบัติงานไม่ได้จนกว่า Admin จะอนุมัติ"
  }
];

export const initialPermissions: Record<RoleType, string[]> = {
  super_admin: ["view_all", "manage_users", "manage_docs", "approve_ops", "assign_coordinator", "manage_vehicles", "assign_vehicles", "borrow_equipment", "view_logs", "view_reports"],
  admin: ["manage_users", "manage_docs", "view_all_docs", "assign_to_ops", "view_reports"],
  head_operation: ["view_all_docs", "approve_ops", "assign_coordinator", "manage_team", "borrow_equipment", "view_logs", "view_reports"],
  coordinator_operation: ["view_own_docs", "acknowledge_doc", "submit_meeting_report", "view_ops_plan"],
  staff_operation: ["view_own_docs", "acknowledge_doc", "view_ops_plan"],
  head_vehicle: ["view_vehicle_needs", "manage_vehicles", "assign_vehicles", "manage_drivers", "borrow_equipment"],
  staff_vehicle: ["view_assigned_vehicles", "acknowledge_trip"],
  pending: ["view_profile"]
};

export const initialUsers: User[] = [
  {
    id: 1,
    username: "superadmin_demo",
    fname: "สมชาย",
    lname: "ยอดบริหาร",
    name: "สมชาย ยอดบริหาร",
    departCode: "DPT-001",
    department: "ศูนย์เทคโนโลยีสารสนเทศ",
    email: "somchai.s@demo.gov.th",
    phone: "081-111-2222",
    cid: "1100100000001",
    role: "super_admin",
    status: "active"
  },
  {
    id: 2,
    username: "admin_demo",
    fname: "ประสิทธิ์",
    lname: "จัดการงาน",
    name: "ประสิทธิ์ จัดการงาน",
    departCode: "DPT-002",
    department: "กองกลาง ฝ่ายสารบรรณ",
    email: "prasit.m@demo.gov.th",
    phone: "081-222-3333",
    cid: "1100100000002",
    role: "admin",
    status: "active"
  },
  {
    id: 3,
    username: "head_op_demo",
    fname: "วิโรจน์",
    lname: "นำปฏิบัติ",
    name: "วิโรจน์ นำปฏิบัติ",
    departCode: "DPT-003",
    department: "Ceremony Operations & Production Division",
    email: "wiroj.h@demo.gov.th",
    phone: "081-333-4444",
    cid: "1100100000003",
    role: "head_operation",
    status: "active"
  },
  {
    id: 4,
    username: "coord_op_demo",
    fname: "ณัฐพงษ์",
    lname: "ประสานมิตร",
    name: "ณัฐพงษ์ ประสานมิตร",
    departCode: "DPT-003",
    department: "Ceremony Operations & Production Division",
    email: "nattapong.c@demo.gov.th",
    phone: "081-444-5555",
    cid: "1100100000004",
    role: "coordinator_operation",
    status: "active"
  },
  {
    id: 5,
    username: "staff_op_demo",
    fname: "กิตติ",
    lname: "พร้อมลงพื้นที่",
    name: "กิตติ พร้อมลงพื้นที่",
    departCode: "DPT-003",
    department: "Ceremony Operations & Production Division",
    email: "kitti.s@demo.gov.th",
    phone: "081-555-6666",
    cid: "1100100000005",
    role: "staff_operation",
    status: "active"
  },
  {
    id: 6,
    username: "head_veh_demo",
    fname: "อนันต์",
    lname: "คุมยานยนต์",
    name: "อนันต์ คุมยานยนต์",
    departCode: "DPT-004",
    department: "ฝ่ายยานพาหนะและขนส่ง",
    email: "anan.v@demo.gov.th",
    phone: "081-666-7777",
    cid: "1100100000006",
    role: "head_vehicle",
    status: "active"
  },
  {
    id: 7,
    username: "staff_veh_demo",
    fname: "วิชัย",
    lname: "ขับปลอดภัย",
    name: "วิชัย ขับปลอดภัย",
    departCode: "DPT-004",
    department: "ฝ่ายยานพาหนะและขนส่ง",
    email: "wichai.d@demo.gov.th",
    phone: "081-777-8888",
    cid: "1100100000007",
    role: "staff_vehicle",
    status: "active"
  },
  {
    id: 8,
    username: "pending_demo",
    fname: "มาณี",
    lname: "รออนุมัติ",
    name: "มาณี รออนุมัติ",
    departCode: "DPT-005",
    department: "สำนักผังเมืองรวม",
    email: "manee.p@demo.gov.th",
    phone: "081-888-9999",
    cid: "1100100000008",
    role: "pending",
    status: "pending"
  }
];

export const initialDocuments: Document[] = [
  {
    id: 1,
    fiscalYear: "2567",
    docNumber: "มท 0701/101",
    sourceDocNumber: "ว 123/2567",
    docName: "โครงการจัดเตรียมพลับพลาพิธีเปิดอาคารศูนย์ราชการกระทรวงมหาดไทย",
    description: "ภารกิจสนับสนุนจัดตั้งพลับพลาพิธีการ ระบบเครื่องเสียง และควบคุมยานพาหนะรับส่งผู้แทนหน่วยงาน",
    jobType: "พิธีการ",
    hostAgency: "สำนักงานปลัดกระทรวงมหาดไทย",
    hostAffiliation: "กระทรวงมหาดไทย",
    coordinator: "ณัฐพงษ์ ประสานมิตร",
    coordinatorPhone: "081-444-5555",
    venueName: "ศูนย์ราชการกระทรวงมหาดไทย",
    venueProvince: "กรุงเทพมหานคร",
    venueDistrict: "คลองสาน",
    venueSubdistrict: "บางลำภูล่าง",
    startDate: "2026-11-10",
    endDate: "2026-11-12",
    receivedDate: "2026-10-15T09:00:00Z",
    createdBy: "ประสิทธิ์ จัดการงาน",
    assignedToId: 4,
    status: "assigned",
    isAcknowledged: true,
    workflowStep: 3,
    vehicleAssigned: true,
    equipmentBorrowed: true,
    teamSet: "ชุดที่ 1"
  },
  {
    id: 2,
    fiscalYear: "2567",
    docNumber: "มท 0701/102",
    sourceDocNumber: "ว 145/2567",
    docName: "พิธีวางศิลาฤกษ์โครงการเขื่อนป้องกันตลิ่งริมแม่น้ำเจ้าพระยา",
    description: "จัดเตรียมสถานที่ อุปกรณ์เครื่องเสียง และจัดชุดปฏิบัติการพร้อมรถบรรทุกขนส่งอุปกรณ์",
    jobType: "วางศิลาฤกษ์",
    hostAgency: "สำนักสนับสนุนและพัฒนาตามผังเมือง",
    hostAffiliation: "กรมโยธาธิการและผังเมือง",
    coordinator: "ยังไม่กำหนด",
    coordinatorPhone: "-",
    venueName: "ริมแม่น้ำเจ้าพระยา อ.เมือง",
    venueProvince: "พระนครศรีอยุธยา",
    venueDistrict: "พระนครศรีอยุธยา",
    venueSubdistrict: "ประตูชัย",
    startDate: "2026-11-20",
    endDate: "2026-11-21",
    receivedDate: "2026-10-16T10:30:00Z",
    createdBy: "ประสิทธิ์ จัดการงาน",
    assignedToId: null,
    status: "pending",
    isAcknowledged: false,
    workflowStep: 1,
    vehicleAssigned: false,
    equipmentBorrowed: false
  },
  {
    id: 3,
    fiscalYear: "2567",
    docNumber: "มท 0701/103",
    sourceDocNumber: "ว 188/2567",
    docName: "การประชุมรับฟังความคิดเห็นของประชาชนด้านการวางผังเมืองรวมจังหวัดชลบุรี",
    description: "ประสานงานห้องประชุม บันทึกรายงานการประชุม และสนับสนุนการลงพื้นที่สำรวจข้อมูลภาคสนาม",
    jobType: "ประชุมรับฟังความคิดเห็น",
    hostAgency: "สำนักผังเมืองรวม",
    hostAffiliation: "กรมโยธาธิการและผังเมือง",
    coordinator: "ยังไม่กำหนด",
    coordinatorPhone: "-",
    venueName: "ศาลากลางจังหวัดชลบุรี",
    venueProvince: "ชลบุรี",
    venueDistrict: "เมืองชลบุรี",
    venueSubdistrict: "บางปลาสร้อย",
    startDate: "2026-11-25",
    endDate: "2026-11-26",
    receivedDate: "2026-10-18T14:00:00Z",
    createdBy: "ประสิทธิ์ จัดการงาน",
    assignedToId: null,
    status: "pending",
    isAcknowledged: false,
    workflowStep: 1,
    vehicleAssigned: false,
    equipmentBorrowed: false
  },
  {
    id: 4,
    fiscalYear: "2567",
    docNumber: "มท 0701/104",
    sourceDocNumber: "ว 210/2567",
    docName: "พิธีเปิดสะพานมิตรภาพข้ามแม่น้ำแม่กลองเฉลิมพระเกียรติ",
    description: "จัดเตรียมพลับพลาพิธี แท่นประดิษฐานพระบรมฉายาลักษณ์ และระบบเครื่องเสียงสำหรับประธานในพิธี",
    jobType: "พิธีการ",
    hostAgency: "สำนักงานโยธาธิการและผังเมืองจังหวัดราชบุรี",
    hostAffiliation: "กรมโยธาธิการและผังเมือง",
    coordinator: "ณัฐพงษ์ ประสานมิตร",
    coordinatorPhone: "081-444-5555",
    venueName: "เชิงสะพานข้ามแม่น้ำแม่กลอง",
    venueProvince: "ราชบุรี",
    venueDistrict: "เมืองราชบุรี",
    venueSubdistrict: "หน้าเมือง",
    startDate: "2026-12-05",
    endDate: "2026-12-06",
    receivedDate: "2026-10-20T11:15:00Z",
    createdBy: "ประสิทธิ์ จัดการงาน",
    assignedToId: 4,
    status: "in_progress",
    isAcknowledged: true,
    workflowStep: 4,
    vehicleAssigned: true,
    equipmentBorrowed: true
  }
];

export const initialEquipments: Equipment[] = [
  {
    id: 1,
    code: "EQ-AUDIO-01",
    name: "ชุดเครื่องเสียงไร้สายภาคสนาม (Field PA System)",
    category: "ระบบเสียง",
    totalQty: 5,
    availableQty: 3,
    unit: "ชุด",
    status: "active"
  },
  {
    id: 2,
    code: "EQ-TENT-01",
    name: "เต็นท์พิธีการทรงจั่ว 5x10 ม. (Ceremony Marquee)",
    category: "โครงสร้าง",
    totalQty: 8,
    availableQty: 6,
    unit: "หลัง",
    status: "active"
  },
  {
    id: 3,
    code: "EQ-ROYAL-01",
    name: "แท่นและกรอบพระบรมฉายาลักษณ์ (Royal Altar & Frame)",
    category: "อุปกรณ์พิธีการ",
    totalQty: 4,
    availableQty: 3,
    unit: "ชุด",
    status: "active"
  },
  {
    id: 4,
    code: "EQ-CHAIR-01",
    name: "ชุดเก้าอี้หลุยส์พิธีการ (ชุด 6 ตัว) (Ceremony Chairs)",
    category: "เฟอร์นิเจอร์",
    totalQty: 10,
    availableQty: 8,
    unit: "ชุด",
    status: "active"
  },
  {
    id: 5,
    code: "EQ-GEN-01",
    name: "เครื่องกำเนิดไฟฟ้าสำรอง 10kVA (Backup Generator)",
    category: "ระบบไฟฟ้า",
    totalQty: 3,
    availableQty: 2,
    unit: "เครื่อง",
    status: "active"
  },
  {
    id: 6,
    code: "EQ-MIC-01",
    name: "ไมโครโฟนโพเดียมไร้สาย (Podium Wireless Mic)",
    category: "ระบบเสียง",
    totalQty: 6,
    availableQty: 5,
    unit: "ตัว",
    status: "active"
  }
];

export const initialVehicles: Vehicle[] = [
  {
    id: 1,
    vehicleType: "รถตู้โดยสาร 11 ที่นั่ง",
    brand: "Toyota Commuter",
    plateLetters: "นข",
    plateNumbers: "4589",
    plateProvince: "กรุงเทพมหานคร",
    licensePlate: "นข 4589 กรุงเทพมหานคร",
    status: "active",
    currentDriver: "วิชัย ขับปลอดภัย"
  },
  {
    id: 2,
    vehicleType: "รถบรรทุก 6 ล้อ สำหรับขนส่งอุปกรณ์",
    brand: "Isuzu Forward",
    plateLetters: "บท",
    plateNumbers: "8812",
    plateProvince: "นนทบุรี",
    licensePlate: "บท 8812 นนทบุรี",
    status: "active",
    currentDriver: "สมพงษ์ มั่นคง"
  },
  {
    id: 3,
    vehicleType: "รถยนต์ส่วนกลางตรวจการ 4 ประตู",
    brand: "Toyota Hilux Revo",
    plateLetters: "กข",
    plateNumbers: "1234",
    plateProvince: "กรุงเทพมหานคร",
    licensePlate: "กข 1234 กรุงเทพมหานคร",
    status: "active",
    currentDriver: "สุรชัย พลขับ"
  },
  {
    id: 4,
    vehicleType: "รถตู้โดยสาร VIP 7 ที่นั่ง",
    brand: "Toyota Alphard",
    plateLetters: "ฮภ",
    plateNumbers: "9988",
    plateProvince: "กรุงเทพมหานคร",
    licensePlate: "ฮภ 9988 กรุงเทพมหานคร",
    status: "active",
    currentDriver: "มาโนชญ์ บริการเด่น"
  }
];

export const initialVehicleAssignments: VehicleAssignment[] = [
  {
    id: 1,
    docId: 1,
    docNumber: "มท 0701/101",
    docName: "โครงการจัดเตรียมพลับพลาพิธีเปิดอาคารศูนย์ราชการกระทรวงมหาดไทย",
    vehicleType: "รถตู้โดยสาร 11 ที่นั่ง",
    licensePlate: "นข 4589 กรุงเทพมหานคร",
    driverName: "วิชัย ขับปลอดภัย",
    assistant1: "กิตติ พร้อมลงพื้นที่",
    assistant2: "-",
    startDate: "2026-11-10",
    endDate: "2026-11-12",
    assignedAt: "2026-10-16T11:00:00Z"
  },
  {
    id: 2,
    docId: 4,
    docNumber: "มท 0701/104",
    docName: "พิธีเปิดสะพานมิตรภาพข้ามแม่น้ำแม่กลองเฉลิมพระเกียรติ",
    vehicleType: "รถบรรทุก 6 ล้อ สำหรับขนส่งอุปกรณ์",
    licensePlate: "บท 8812 นนทบุรี",
    driverName: "วิชัย ขับปลอดภัย",
    assistant1: "สมปอง ว่องไว",
    assistant2: "-",
    startDate: "2026-12-05",
    endDate: "2026-12-06",
    assignedAt: "2026-10-21T09:30:00Z"
  }
];

export const initialBorrowings: Borrowing[] = [
  {
    id: 1,
    docId: 1,
    equipmentCode: "EQ-AUDIO-01",
    equipmentName: "ชุดเครื่องเสียงไร้สายภาคสนาม",
    quantity: 1,
    unit: "ชุด",
    borrowDate: "2026-11-09",
    returnDate: "2026-11-13",
    status: "borrowed",
    borrowerName: "ณัฐพงษ์ ประสานมิตร"
  },
  {
    id: 2,
    docId: 1,
    equipmentCode: "EQ-ROYAL-01",
    equipmentName: "แท่นและกรอบพระบรมฉายาลักษณ์",
    quantity: 1,
    unit: "ชุด",
    borrowDate: "2026-11-09",
    returnDate: "2026-11-13",
    status: "borrowed",
    borrowerName: "ณัฐพงษ์ ประสานมิตร"
  }
];

export const initialMeetingReports: MeetingReport[] = [
  {
    id: 1,
    docId: 1,
    meetingDate: "2026-10-25",
    meetingTime: "10:00 น.",
    location: "ห้องประชุมสารบรรณ 1 กรมโยธาธิการและผังเมือง",
    chairmanName: "นายวิโรจน์ นำปฏิบัติ (หัวหน้าฝ่ายปฏิบัติการ)",
    topic: "การเตรียมความพร้อมระบบพลับพลาพิธีและกำหนดการร่วมกับหน่วยงานเจ้าภาพ",
    summary: "ที่ประชุมมีมติเห็นชอบผังจัดตั้งพลับพลาพิธีขนาด 12x20 เมตร ระบบเสียงไร้สาย 4 ช่องสัญญาณ และกำหนดให้ทีมยานพาหนะเข้าพื้นที่ล่วงหน้า 1 วันเพื่อจัดวางอุปกรณ์",
    reporterName: "ณัฐพงษ์ ประสานมิตร",
    createdAt: "2026-10-25T14:30:00Z"
  }
];

export const initialLogs: SystemLog[] = [
  {
    id: 1,
    action: "CREATE_DOC",
    detail: "ลงรับหนังสือราชการใหม่ มท 0701/101 โครงการจัดเตรียมพลับพลาพิธีเปิดอาคารศูนย์ราชการฯ",
    user: "ประสิทธิ์ จัดการงาน (admin)",
    timestamp: "2026-10-15T09:00:00Z"
  },
  {
    id: 2,
    action: "ASSIGN_COORDINATOR",
    detail: "มอบหมายผู้ประสานงานภารกิจ มท 0701/101: ณัฐพงษ์ ประสานมิตร",
    user: "วิโรจน์ นำปฏิบัติ (head_operation)",
    timestamp: "2026-10-15T10:00:00Z"
  },
  {
    id: 3,
    action: "ASSIGN_VEHICLE",
    detail: "จัดสรรรถตู้ นข 4589 กทม. และพลขับ วิชัย ขับปลอดภัย ให้แก่ภารกิจ มท 0701/101",
    user: "อนันต์ คุมยานยนต์ (head_vehicle)",
    timestamp: "2026-10-16T11:00:00Z"
  },
  {
    id: 4,
    action: "BORROW_EQUIPMENT",
    detail: "เบิกยืมชุดเครื่องเสียงไร้สาย และแท่นพระบรมฉายาลักษณ์ สำหรับภารกิจ มท 0701/101",
    user: "ณัฐพงษ์ ประสานมิตร (coordinator_operation)",
    timestamp: "2026-10-17T09:30:00Z"
  }
];

export const initialTeamSets: TeamSet[] = [
  {
    setName: "ชุดที่ 1",
    members: ["กิตติ พร้อมลงพื้นที่", "สมเกียรติ มั่นคง"]
  },
  {
    setName: "ชุดที่ 2",
    members: ["สมศักดิ์ มุ่งมั่น", "วิเชียร บริสุทธิ์"]
  },
  {
    setName: "ชุดที่ 3",
    members: ["อนุรักษ์ เจริญผล"]
  }
];

export const initialVehicleTypes: string[] = [
  "รถตู้โดยสาร 11 ที่นั่ง",
  "รถบรรทุก 6 ล้อ สำหรับขนส่งอุปกรณ์",
  "รถยนต์ส่วนกลางตรวจการ 4 ประตู",
  "รถตู้โดยสาร VIP 7 ที่นั่ง"
];

export const initialDriverQualifications = [
  {
    userId: 6,
    allowedVehicles: ["รถตู้โดยสาร 11 ที่นั่ง", "รถยนต์ส่วนกลางตรวจการ 4 ประตู"]
  },
  {
    userId: 7,
    allowedVehicles: ["รถตู้โดยสาร 11 ที่นั่ง", "รถยนต์ส่วนกลางตรวจการ 4 ประตู", "รถบรรทุก 6 ล้อ สำหรับขนส่งอุปกรณ์"]
  }
];


