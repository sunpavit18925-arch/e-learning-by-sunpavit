import { Request, Response, NextFunction } from "express";

export const notFoundHandler = (req: Request, res: Response): void => {
  res.status(404).json({
    success: false,
    error: `Endpoint not found: ${req.method} ${req.originalUrl}`
  });
};

export const errorHandler = (err: Error, req: Request, res: Response, next: NextFunction): void => {
  console.error(`[API Error] ${req.method} ${req.url}:`, err);
  res.status(500).json({
    success: false,
    error: "Internal Server Error",
    message: err.message
  });
};

