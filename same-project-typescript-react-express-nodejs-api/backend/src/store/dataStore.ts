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
  DriverQualification,
  RoleType
} from "../types/index.js";
import {
  initialRoles,
  initialPermissions,
  initialUsers,
  initialDocuments,
  initialEquipments,
  initialVehicles,
  initialVehicleAssignments,
  initialBorrowings,
  initialMeetingReports,
  initialLogs,
  initialTeamSets,
  initialVehicleTypes,
  initialDriverQualifications
} from "./initialData.js";

// In-Memory Data Store with Deep-Clone Snapshot Reset
export class MockDataStore {
  public roles!: Role[];
  public permissions!: Record<RoleType, string[]>;
  public users!: User[];
  public documents!: Document[];
  public equipments!: Equipment[];
  public vehicles!: Vehicle[];
  public vehicleAssignments!: VehicleAssignment[];
  public borrowings!: Borrowing[];
  public meetingReports!: MeetingReport[];
  public logs!: SystemLog[];
  public teamSets!: TeamSet[];
  public vehicleTypes!: string[];
  public driverQualifications!: DriverQualification[];
  public lastReset!: string;

  constructor() {
    this.reset();
  }

  public reset(): void {
    this.roles = JSON.parse(JSON.stringify(initialRoles));
    this.permissions = JSON.parse(JSON.stringify(initialPermissions));
    this.users = JSON.parse(JSON.stringify(initialUsers));
    this.documents = JSON.parse(JSON.stringify(initialDocuments));
    this.equipments = JSON.parse(JSON.stringify(initialEquipments));
    this.vehicles = JSON.parse(JSON.stringify(initialVehicles));
    this.vehicleAssignments = JSON.parse(JSON.stringify(initialVehicleAssignments));
    this.borrowings = JSON.parse(JSON.stringify(initialBorrowings));
    this.meetingReports = JSON.parse(JSON.stringify(initialMeetingReports));
    this.logs = JSON.parse(JSON.stringify(initialLogs));
    this.teamSets = JSON.parse(JSON.stringify(initialTeamSets));
    this.vehicleTypes = JSON.parse(JSON.stringify(initialVehicleTypes));
    this.driverQualifications = JSON.parse(JSON.stringify(initialDriverQualifications));
    this.lastReset = new Date().toISOString();
  }

  public addLog(action: string, detail: string, user: string = "System Demo"): SystemLog {
    const newLog: SystemLog = {
      id: this.logs.length + 1,
      action,
      detail,
      user,
      timestamp: new Date().toISOString()
    };
    this.logs.unshift(newLog);
    return newLog;
  }
}

export const store = new MockDataStore();

