using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Runtime.InteropServices;
using System.Text;
using System.Threading;
using System.Windows.Forms;

namespace SistemaEmpleados
{
    public class Program
    {
        private static Process phpProcess = null;
        private static NotifyIcon trayIcon = null;
        private static int serverPort = 8040;
        private static string appDir = "";
        private static string urlSistema = "";
        private static Mutex singleInstanceMutex = null;

        // ── Job Object: fuerza cierre de PHP al cerrar la app ──────────────────
        [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
        static extern IntPtr CreateJobObject(IntPtr lpJobAttributes, string lpName);
        [DllImport("kernel32.dll", SetLastError = true)]
        static extern bool SetInformationJobObject(IntPtr hJob, int JobObjectInfoClass, IntPtr lpJobObjectInfo, uint cbJobObjectInfoLength);
        [DllImport("kernel32.dll", SetLastError = true)]
        static extern bool AssignProcessToJobObject(IntPtr hJob, IntPtr hProcess);

        [StructLayout(LayoutKind.Sequential)]
        struct JOBOBJECT_BASIC_LIMIT_INFORMATION
        {
            public long PerProcessUserTimeLimit, PerJobUserTimeLimit;
            public uint LimitFlags;
            public UIntPtr MinimumWorkingSetSize, MaximumWorkingSetSize;
            public uint ActiveProcessLimit;
            public UIntPtr Affinity;
            public uint PriorityClass, SchedulingClass;
        }
        [StructLayout(LayoutKind.Sequential)]
        struct IO_COUNTERS
        {
            public ulong ReadOperationCount, WriteOperationCount, OtherOperationCount;
            public ulong ReadTransferCount, WriteTransferCount, OtherTransferCount;
        }
        [StructLayout(LayoutKind.Sequential)]
        struct JOBOBJECT_EXTENDED_LIMIT_INFORMATION
        {
            public JOBOBJECT_BASIC_LIMIT_INFORMATION BasicLimitInformation;
            public IO_COUNTERS IoInfo;
            public UIntPtr ProcessMemoryLimit, JobMemoryLimit, PeakProcessMemoryLimit, PeakJobMemoryLimit;
        }

        private static IntPtr jobHandle = IntPtr.Zero;

        private static void InitJobObject()
        {
            try
            {
                jobHandle = CreateJobObject(IntPtr.Zero, null);
                if (jobHandle == IntPtr.Zero) return;
                var info = new JOBOBJECT_EXTENDED_LIMIT_INFORMATION();
                info.BasicLimitInformation.LimitFlags = 0x2000; // JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE
                int len = Marshal.SizeOf(typeof(JOBOBJECT_EXTENDED_LIMIT_INFORMATION));
                IntPtr pInfo = Marshal.AllocHGlobal(len);
                try { Marshal.StructureToPtr(info, pInfo, false); SetInformationJobObject(jobHandle, 9, pInfo, (uint)len); }
                finally { Marshal.FreeHGlobal(pInfo); }
            }
            catch { }
        }

        private static void TrackProcess(IntPtr hProcess)
        {
            try { if (jobHandle != IntPtr.Zero) AssignProcessToJobObject(jobHandle, hProcess); }
            catch { }
        }

        // ── Entrada principal ──────────────────────────────────────────────────
        [STAThread]
        public static void Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            appDir = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\', '/');

            bool createdNew;
            singleInstanceMutex = new Mutex(true, "Global\\SistemaEmpleados_SingleInstance_v2", out createdNew);

            if (!createdNew)
            {
                // Ya hay una instancia corriendo: solo abre el navegador
                if (!string.IsNullOrEmpty(urlSistema))
                    Process.Start(urlSistema);
                else
                    Process.Start(string.Format("http://localhost:{0}/public/html/access.html", serverPort));
                return;
            }

            InitJobObject();

            // Elimina procesos PHP huérfanos de sesiones anteriores antes de buscar puerto
            LimpiarProcesosOrfanos();

            string phpBin = LocalizarPhp();
            if (string.IsNullOrEmpty(phpBin))
            {
                MessageBox.Show(
                    "No se encontró PHP.\n\nColoca los binarios en la carpeta 'php\\' o instala XAMPP.",
                    "Sistema de Gestión de Personal",
                    MessageBoxButtons.OK, MessageBoxIcon.Error);
                return;
            }

            serverPort = ObtenerPuertoLibre(8040);
            if (serverPort == 0)
            {
                MessageBox.Show("No se encontró un puerto disponible (8040-8090).", "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return;
            }

            if (!IniciarServidor(phpBin))
            {
                return;
            }

            urlSistema = string.Format("http://localhost:{0}/public/html/access.html", serverPort);
            
            // Health check: verificar que el servidor responde antes de abrir el navegador
            bool servidorListo = EsperarServidorListo(serverPort, 3000);
            if (servidorListo)
            {
                try
                {
                    Process.Start(urlSistema);
                }
                catch (Exception ex)
                {
                    MessageBox.Show(
                        string.Format("El servidor inició correctamente en {0}, pero no se pudo abrir el navegador automáticamente.\n\nPor favor, ábrelo manualmente.\nDetalle: {1}", urlSistema, ex.Message),
                        "Sistema de Gestión de Personal",
                        MessageBoxButtons.OK,
                        MessageBoxIcon.Information
                    );
                }
            }
            else
            {
                MessageBox.Show(
                    "El servidor local de PHP no respondió a tiempo.\nVerifica si un antivirus o cortafuegos está bloqueando la conexión interna.",
                    "Sistema de Gestión de Personal — Advertencia",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Warning
                );
            }

            CrearIconoBandeja();
            Application.Run();
        }

        // ── Health Check: Espera activa a que el servidor HTTP esté listo ────────
        private static bool EsperarServidorListo(int puerto, int tiempoMaximoMs)
        {
            var sw = Stopwatch.StartNew();
            while (sw.ElapsedMilliseconds < tiempoMaximoMs)
            {
                if (phpProcess != null && phpProcess.HasExited)
                {
                    return false;
                }

                try
                {
                    using (var client = new TcpClient())
                    {
                        var result = client.BeginConnect(IPAddress.Loopback, puerto, null, null);
                        bool exito = result.AsyncWaitHandle.WaitOne(200, false);
                        if (exito && client.Connected)
                        {
                            client.EndConnect(result);
                            return true;
                        }
                    }
                }
                catch { }

                Thread.Sleep(100);
            }
            return false;
        }

        // ── Localizar PHP ──────────────────────────────────────────────────────
        private static string LocalizarPhp()
        {
            string[] candidatos = new[]
            {
                Path.Combine(appDir, "php", "php.exe"),
                "php",
                @"C:\xampp\php\php.exe",
                @"C:\laragon\bin\php\php.exe",
                @"C:\php\php.exe"
            };
            foreach (string c in candidatos)
            {
                if (File.Exists(c)) return c;
                if (c == "php")
                {
                    try
                    {
                        var p = new Process();
                        p.StartInfo = new ProcessStartInfo("php", "-v") { UseShellExecute = false, CreateNoWindow = true, RedirectStandardOutput = true };
                        p.Start(); p.WaitForExit(2000);
                        if (p.ExitCode == 0) return "php";
                    }
                    catch { }
                }
            }
            return null;
        }

        // ── Liberar puertos ocupados por procesos PHP huérfanos ────────────────
        // Mata procesos php.exe que provengan del mismo directorio de la app
        // y que sigan ocupando puertos del rango 8040-8090 de sesiones anteriores.
        private static void LimpiarProcesosOrfanos()
        {
            string phpDir = Path.Combine(appDir, "php") + Path.DirectorySeparatorChar;

            Process[] phpProcs;
            try { phpProcs = Process.GetProcessesByName("php"); }
            catch { return; }

            if (phpProcs == null || phpProcs.Length == 0) return;

            int eliminados = 0;
            foreach (Process proc in phpProcs)
            {
                try
                {
                    // Compara la ruta del ejecutable con nuestro directorio php/
                    string exePath = "";
                    try { exePath = proc.MainModule.FileName; } catch { continue; }

                    if (string.IsNullOrEmpty(exePath)) continue;

                    // Solo mata procesos cuyo php.exe está dentro de NUESTRA carpeta php/
                    if (exePath.StartsWith(phpDir, StringComparison.OrdinalIgnoreCase))
                    {
                        try
                        {
                            proc.Kill();
                            proc.WaitForExit(2000);
                            eliminados++;
                        }
                        catch { }
                    }
                }
                catch { }
                finally
                {
                    try { proc.Dispose(); } catch { }
                }
            }

            if (eliminados > 0)
            {
                // Pausa breve para que el SO libere los puertos
                Thread.Sleep(500);
            }
        }

        // ── Puerto libre ───────────────────────────────────────────────────────
        private static int ObtenerPuertoLibre(int preferido)
        {
            for (int p = preferido; p <= 8090; p++)
            {
                try
                {
                    var listener = new TcpListener(IPAddress.Loopback, p);
                    listener.ExclusiveAddressUse = true;
                    listener.Start();
                    listener.Stop();
                    return p;
                }
                catch { }
            }
            return 0;
        }

        // ── Lanzar servidor PHP ────────────────────────────────────────────────
        private static bool IniciarServidor(string phpBin)
        {
            // Rutas adaptadas a la nueva estructura: config/php.ini y config/router.php
            string phpIni    = Path.Combine(appDir, "config", "php.ini");
            string routerPhp = Path.Combine(appDir, "config", "router.php");
            string extDir    = Path.Combine(Path.GetDirectoryName(phpBin), "ext");

            if (!File.Exists(routerPhp))
            {
                MessageBox.Show("No se encontró config\\router.php.", "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return false;
            }

            var psi = new ProcessStartInfo
            {
                FileName         = phpBin,
                Arguments        = string.Format("-c \"{0}\" -d \"extension_dir={1}\" -S 127.0.0.1:{2} \"{3}\"",
                                                 phpIni, extDir, serverPort, routerPhp),
                WorkingDirectory = appDir,
                UseShellExecute  = false,
                CreateNoWindow   = true
            };

            phpProcess = Process.Start(psi);
            if (phpProcess == null)
            {
                MessageBox.Show("No se pudo iniciar el proceso de PHP.", "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return false;
            }

            TrackProcess(phpProcess.Handle);

            Thread.Sleep(300);
            if (phpProcess.HasExited)
            {
                MessageBox.Show(
                    string.Format("El servidor PHP se cerró inesperadamente al arrancar (Código de salida: {0}).\n\nVerifica que los archivos en 'php\\' y 'config\\' no hayan sido alterados.", phpProcess.ExitCode),
                    "Error al arrancar PHP",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error
                );
                return false;
            }

            return true;
        }

        // ── Icono de bandeja del sistema ───────────────────────────────────────
        private static void CrearIconoBandeja()
        {
            Icon icono = null;
            try
            {
                icono = Icon.ExtractAssociatedIcon(Application.ExecutablePath);
            }
            catch { }

            if (icono == null)
            {
                string iconPath = Path.Combine(appDir, "config", "app.ico");
                if (File.Exists(iconPath))
                {
                    try { icono = new Icon(iconPath); } catch { }
                }
            }
            if (icono == null)
            {
                // Icono genérico de aplicación
                var bmp = new Bitmap(16, 16);
                using (var g = Graphics.FromImage(bmp))
                {
                    g.Clear(Color.FromArgb(37, 99, 235));
                    g.FillEllipse(Brushes.White, 3, 3, 10, 10);
                }
                icono = Icon.FromHandle(bmp.GetHicon());
            }

            trayIcon = new NotifyIcon
            {
                Icon    = icono,
                Text    = string.Format("Gestión de Personal — Puerto {0}", serverPort),
                Visible = true
            };

            var menu = new ContextMenu();
            menu.MenuItems.Add("🌐  Abrir en navegador", (s, e) => Process.Start(urlSistema));
            menu.MenuItems.Add("-");
            menu.MenuItems.Add(string.Format("Puerto: {0}", serverPort)).Enabled = false;
            menu.MenuItems.Add("-");
            menu.MenuItems.Add("❌  Detener y salir", (s, e) => Salir());

            trayIcon.ContextMenu = menu;
            trayIcon.DoubleClick += (s, e) => Process.Start(urlSistema);

            trayIcon.ShowBalloonTip(3000,
                "Sistema de Gestión de Personal",
                string.Format("Servidor iniciado en el puerto {0}.\nDoble clic para abrir.", serverPort),
                ToolTipIcon.Info);
        }

        // ── Salir limpiamente ──────────────────────────────────────────────────
        private static void Salir()
        {
            if (trayIcon != null) { trayIcon.Visible = false; trayIcon.Dispose(); }
            try
            {
                if (phpProcess != null && !phpProcess.HasExited)
                {
                    phpProcess.Kill();
                    phpProcess.WaitForExit(3000);
                }
            }
            catch { }
            if (singleInstanceMutex != null) singleInstanceMutex.ReleaseMutex();
            Application.Exit();
        }
    }
}
