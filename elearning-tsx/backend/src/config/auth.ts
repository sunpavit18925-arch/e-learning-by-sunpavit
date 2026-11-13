import 'dotenv/config';
const secret = process.env.JWT_SECRET;
if (!secret || secret === 'replace-with-a-long-random-secret') throw new Error('Set JWT_SECRET in backend/.env');
export { secret };
