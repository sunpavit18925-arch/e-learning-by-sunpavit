import { Router } from "express";
import { checkDemoRole } from "../middlewares/auth.js";
import {
  getRoles,
  getUsers,
  updateUserRole,
  getDashboardStats,
  getDocuments,
  getDocumentById,
  createDocument,
  updateDocument,
  deleteDocument,
  assignCoordinator,
  acknowledgeDocument,
  submitMeetingReport,
  completeDocument,
  getVehicles,
  assignVehicle,
  getEquipments,
  borrowEquipment,
  returnEquipment,
  getSystemLogs,
  resetDemoData,
  getTeamSets,
  createTeamSet,
  assignStaffToTeam,
  removeStaffFromTeam,
  assignTeamToDocument,
  getVehicleTypes,
  createVehicleType,
  updateVehicleType,
  deleteVehicleType,
  createVehicle,
  updateVehicle,
  deleteVehicle,
  getDriverQualifications,
  saveDriverQualifications,
  assignCoordinatorRole,
  revokeCoordinatorRole
} from "../controllers/demoController.js";

const router = Router();

// General / Discovery
router.get("/roles", getRoles);
router.get("/users", getUsers);
router.patch("/users/:id/role", checkDemoRole(["super_admin", "admin"]), updateUserRole);
router.get("/dashboard", checkDemoRole(), getDashboardStats);
router.get("/logs", checkDemoRole(), getSystemLogs);
router.post("/reset", resetDemoData);

// Documents & Operations Workflow
router.get("/documents", checkDemoRole(), getDocuments);
router.get("/documents/:id", checkDemoRole(), getDocumentById);
router.post("/documents", checkDemoRole(["super_admin", "admin"]), createDocument);
router.put("/documents/:id", checkDemoRole(["super_admin", "admin"]), updateDocument);
router.delete("/documents/:id", checkDemoRole(["super_admin", "admin"]), deleteDocument);
router.post("/documents/:id/assign-coordinator", checkDemoRole(["super_admin", "head_operation"]), assignCoordinator);
router.post("/documents/:id/acknowledge", checkDemoRole(["super_admin", "coordinator_operation", "staff_operation"]), acknowledgeDocument);
router.post("/documents/:id/meeting-report", checkDemoRole(["super_admin", "coordinator_operation"]), submitMeetingReport);
router.post("/documents/:id/complete", checkDemoRole(["super_admin", "head_operation", "admin"]), completeDocument);

// Vehicles & Fleet Management
router.get("/vehicles", checkDemoRole(), getVehicles);
router.post("/vehicles", checkDemoRole(["super_admin", "head_vehicle"]), createVehicle);
router.put("/vehicles/:id", checkDemoRole(["super_admin", "head_vehicle"]), updateVehicle);
router.delete("/vehicles/:id", checkDemoRole(["super_admin", "head_vehicle"]), deleteVehicle);
router.post("/vehicles/assign", checkDemoRole(["super_admin", "head_vehicle"]), assignVehicle);

// Vehicle Types & Drivers
router.get("/vehicle-types", checkDemoRole(), getVehicleTypes);
router.post("/vehicle-types", checkDemoRole(["super_admin", "head_vehicle"]), createVehicleType);
router.put("/vehicle-types", checkDemoRole(["super_admin", "head_vehicle"]), updateVehicleType);
router.delete("/vehicle-types/:typeName", checkDemoRole(["super_admin", "head_vehicle"]), deleteVehicleType);
router.get("/drivers/qualifications", checkDemoRole(), getDriverQualifications);
router.post("/drivers/qualifications", checkDemoRole(["super_admin", "head_vehicle"]), saveDriverQualifications);

// Team Sets Management
router.get("/teams", checkDemoRole(), getTeamSets);
router.post("/teams", checkDemoRole(["super_admin", "head_operation"]), createTeamSet);
router.post("/teams/assign", checkDemoRole(["super_admin", "head_operation"]), assignStaffToTeam);
router.post("/teams/remove", checkDemoRole(["super_admin", "head_operation"]), removeStaffFromTeam);
router.post("/documents/:id/assign-team", checkDemoRole(["super_admin", "head_operation"]), assignTeamToDocument);

// Coordinator Promotion / Revocation
router.post("/coordinators/assign", checkDemoRole(["super_admin", "head_operation"]), assignCoordinatorRole);
router.post("/coordinators/revoke", checkDemoRole(["super_admin", "head_operation"]), revokeCoordinatorRole);

// Equipments Management
router.get("/equipments", checkDemoRole(), getEquipments);
router.post("/equipments/borrow", checkDemoRole(["super_admin", "head_operation", "head_vehicle", "coordinator_operation"]), borrowEquipment);
router.post("/equipments/borrowings/:id/return", checkDemoRole(["super_admin", "head_operation", "head_vehicle"]), returnEquipment);

export default router;

